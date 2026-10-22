<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Throwable;

class SmsGateway
{
    public const BATCH = 50;

    public static function enabled(): bool
    {
        return SiteSettings::get('sms_enabled', '0') === '1' && in_array(SiteSettings::get('sms_provider', 'manual'), ['bulksmsbd', 'twilio'], true);
    }

    /** Digits only, Bangladeshi local numbers (01XXXXXXXXX) become 8801XXXXXXXXX. */
    public static function normalize(string $phone): string
    {
        $digits = preg_replace('/\D+/', '', $phone) ?? '';

        return str_starts_with($digits, '0') ? '880'.substr($digits, 1) : $digits;
    }

    /**
     * Try to deliver queued messages through the configured provider.
     *
     * @param  list<int>|null  $ids  only these log rows, otherwise the oldest queued ones
     * @return array{sent: int, failed: int}
     */
    public static function deliver(?array $ids = null, int $limit = self::BATCH): array
    {
        $result = ['sent' => 0, 'failed' => 0];
        if (! self::enabled()) {
            return $result;
        }

        $rows = DB::table('sms_logs')->where('status', 'queued')->when($ids !== null, fn ($q) => $q->whereIn('id', $ids))->orderBy('id')->limit($limit)->get();
        foreach ($rows as $row) {
            [$ok, $detail] = self::send($row->phone, $row->message);
            DB::table('sms_logs')->where('id', $row->id)->update(['status' => $ok ? 'sent' : 'failed', 'detail' => $ok ? null : mb_substr($detail, 0, 250), 'updated_at' => now()]);
            $ok ? $result['sent']++ : $result['failed']++;
        }

        return $result;
    }

    /**
     * Queue a message and, when a gateway is on, deliver it straight away.
     *
     * @return int the log row id
     */
    public static function queue(string $phone, ?string $recipient, string $message, ?int $by = null): int
    {
        $id = DB::table('sms_logs')->insertGetId([
            'phone' => $phone, 'recipient' => $recipient, 'message' => mb_substr($message, 0, 500), 'status' => 'queued',
            'sent_by' => $by, 'created_at' => now(), 'updated_at' => now(),
        ]);
        self::deliver([$id], 1);

        return $id;
    }

    /** @return array{0: bool, 1: string} */
    private static function send(string $phone, string $message): array
    {
        $number = self::normalize($phone);
        if (strlen($number) < 8) {
            return [false, 'The phone number is not valid.'];
        }

        try {
            return match (SiteSettings::get('sms_provider', 'manual')) {
                'bulksmsbd' => self::viaBulkSmsBd($number, $message),
                'twilio' => self::viaTwilio($number, $message),
                default => [false, 'No SMS provider is selected.'],
            };
        } catch (Throwable $e) {
            return [false, 'Could not reach the SMS provider: '.$e->getMessage()];
        }
    }

    /** @return array{0: bool, 1: string} */
    private static function viaBulkSmsBd(string $number, string $message): array
    {
        $key = config('services.bulksmsbd.key');
        if (blank($key)) {
            return [false, 'BULKSMSBD_API_KEY is missing from the .env file.'];
        }

        $response = Http::asForm()->timeout(8)->post(config('services.bulksmsbd.url'), [
            'api_key' => $key, 'type' => 'text', 'number' => $number, 'message' => $message,
            'senderid' => SiteSettings::get('sms_sender_id') ?: config('services.bulksmsbd.sender_id'),
        ]);

        // The provider answers 202 when it accepts the message.
        return (int) $response->json('response_code') === 202 ? [true, ''] : [false, (string) ($response->json('error_message') ?: 'The provider refused the message.')];
    }

    /** @return array{0: bool, 1: string} */
    private static function viaTwilio(string $number, string $message): array
    {
        [$sid, $token] = [config('services.twilio.sid'), config('services.twilio.token')];
        $from = SiteSettings::get('sms_sender_id') ?: config('services.twilio.from');
        if (blank($sid) || blank($token) || blank($from)) {
            return [false, 'TWILIO_SID, TWILIO_TOKEN and a sender (TWILIO_FROM or Sender ID) are required.'];
        }

        $response = Http::withBasicAuth($sid, $token)->asForm()->timeout(8)->post("https://api.twilio.com/2010-04-01/Accounts/{$sid}/Messages.json", ['To' => '+'.$number, 'From' => $from, 'Body' => $message]);

        return $response->status() === 201 ? [true, ''] : [false, (string) ($response->json('message') ?: 'The provider refused the message.')];
    }
}
