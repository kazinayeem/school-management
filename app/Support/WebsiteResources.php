<?php

namespace App\Support;

class WebsiteResources
{
    /**
     * Field types: text, textarea, number, date, select, checkbox.
     *
     * @return array<string, array<string, mixed>>
     */
    public static function all(): array
    {
        return [
            'pages' => [
                'label' => 'Pages',
                'subtitle' => 'Static pages shown on the public website, such as About or Facilities.',
                'table' => 'website_pages',
                'order' => ['updated_at', 'desc'],
                'columns' => ['title', 'slug', 'published'],
                'fields' => [
                    ['name' => 'title', 'label' => 'Title', 'type' => 'text', 'rules' => 'required|string|max:150'],
                    ['name' => 'slug', 'label' => 'URL slug', 'type' => 'text', 'rules' => 'nullable|string|max:150|alpha_dash', 'hint' => 'Leave empty to build it from the title.'],
                    ['name' => 'body', 'label' => 'Content', 'type' => 'textarea', 'rules' => 'nullable|string'],
                    ['name' => 'published', 'label' => 'Published', 'type' => 'checkbox', 'default' => true],
                ],
            ],
            'banners' => [
                'label' => 'Hero Banners',
                'subtitle' => 'Large headline blocks at the top of the home page.',
                'table' => 'hero_banners',
                'order' => ['sort_order', 'asc'],
                'columns' => ['title', 'subtitle', 'sort_order', 'active'],
                'fields' => [
                    ['name' => 'title', 'label' => 'Headline', 'type' => 'text', 'rules' => 'required|string|max:150'],
                    ['name' => 'subtitle', 'label' => 'Sub-headline', 'type' => 'text', 'rules' => 'nullable|string|max:255'],
                    ['name' => 'button_label', 'label' => 'Button text', 'type' => 'text', 'rules' => 'nullable|string|max:60'],
                    ['name' => 'button_url', 'label' => 'Button link', 'type' => 'text', 'rules' => 'nullable|string|max:255'],
                    ['name' => 'sort_order', 'label' => 'Order', 'type' => 'number', 'rules' => 'nullable|integer|min:0', 'default' => 0],
                    ['name' => 'active', 'label' => 'Active', 'type' => 'checkbox', 'default' => true],
                ],
            ],
            'events' => [
                'label' => 'Events',
                'subtitle' => 'Upcoming school events listed on the website.',
                'table' => 'website_events',
                'order' => ['starts_on', 'desc'],
                'columns' => ['title', 'starts_on', 'venue', 'published'],
                'fields' => [
                    ['name' => 'title', 'label' => 'Title', 'type' => 'text', 'rules' => 'required|string|max:150'],
                    ['name' => 'starts_on', 'label' => 'Date', 'type' => 'date', 'rules' => 'required|date'],
                    ['name' => 'venue', 'label' => 'Venue', 'type' => 'text', 'rules' => 'nullable|string|max:150'],
                    ['name' => 'body', 'label' => 'Details', 'type' => 'textarea', 'rules' => 'nullable|string'],
                    ['name' => 'published', 'label' => 'Published', 'type' => 'checkbox', 'default' => true],
                ],
            ],
            'gallery' => [
                'label' => 'Gallery',
                'subtitle' => 'Photos grouped into albums for the public gallery.',
                'table' => 'gallery_items',
                'order' => ['sort_order', 'asc'],
                'columns' => ['title', 'album', 'caption', 'sort_order'],
                'fields' => [
                    ['name' => 'title', 'label' => 'Title', 'type' => 'text', 'rules' => 'required|string|max:150'],
                    ['name' => 'album', 'label' => 'Album', 'type' => 'text', 'rules' => 'nullable|string|max:100'],
                    ['name' => 'caption', 'label' => 'Caption', 'type' => 'text', 'rules' => 'nullable|string|max:255'],
                    ['name' => 'image_url', 'label' => 'Image link', 'type' => 'text', 'rules' => 'nullable|string|max:500'],
                    ['name' => 'sort_order', 'label' => 'Order', 'type' => 'number', 'rules' => 'nullable|integer|min:0', 'default' => 0],
                ],
            ],
            'testimonials' => [
                'label' => 'Testimonials',
                'subtitle' => 'Quotes from parents and students.',
                'table' => 'testimonials',
                'order' => ['id', 'desc'],
                'columns' => ['name', 'role', 'rating', 'published'],
                'fields' => [
                    ['name' => 'name', 'label' => 'Name', 'type' => 'text', 'rules' => 'required|string|max:100'],
                    ['name' => 'role', 'label' => 'Role', 'type' => 'text', 'rules' => 'nullable|string|max:100'],
                    ['name' => 'quote', 'label' => 'Quote', 'type' => 'textarea', 'rules' => 'required|string'],
                    ['name' => 'rating', 'label' => 'Rating (1-5)', 'type' => 'number', 'rules' => 'required|integer|min:1|max:5', 'default' => 5],
                    ['name' => 'published', 'label' => 'Published', 'type' => 'checkbox', 'default' => true],
                ],
            ],
            'faqs' => [
                'label' => 'FAQs',
                'subtitle' => 'Common questions answered for families.',
                'table' => 'faqs',
                'order' => ['sort_order', 'asc'],
                'columns' => ['question', 'sort_order', 'published'],
                'fields' => [
                    ['name' => 'question', 'label' => 'Question', 'type' => 'text', 'rules' => 'required|string|max:255'],
                    ['name' => 'answer', 'label' => 'Answer', 'type' => 'textarea', 'rules' => 'required|string'],
                    ['name' => 'sort_order', 'label' => 'Order', 'type' => 'number', 'rules' => 'nullable|integer|min:0', 'default' => 0],
                    ['name' => 'published', 'label' => 'Published', 'type' => 'checkbox', 'default' => true],
                ],
            ],
        ];
    }

    /** @return list<array<string, mixed>> */
    public static function settingFields(): array
    {
        return [
            ['name' => 'school_name', 'label' => 'School name', 'type' => 'text', 'rules' => 'required|string|max:150', 'default' => 'Borno School'],
            ['name' => 'tagline', 'label' => 'Tagline', 'type' => 'text', 'rules' => 'nullable|string|max:255', 'default' => ''],
            ['name' => 'phone', 'label' => 'Phone', 'type' => 'text', 'rules' => 'nullable|string|max:50', 'default' => ''],
            ['name' => 'email', 'label' => 'Contact email', 'type' => 'text', 'rules' => 'nullable|email|max:150', 'default' => ''],
            ['name' => 'address', 'label' => 'Address', 'type' => 'textarea', 'rules' => 'nullable|string|max:500', 'default' => ''],
            ['name' => 'facebook_url', 'label' => 'Facebook link', 'type' => 'text', 'rules' => 'nullable|string|max:255', 'default' => ''],
            ['name' => 'footer_text', 'label' => 'Footer text', 'type' => 'text', 'rules' => 'nullable|string|max:255', 'default' => ''],
            ['name' => 'admission_open', 'label' => 'Admissions open', 'type' => 'checkbox', 'default' => true],
        ];
    }

    public static function slugs(): string
    {
        return implode('|', [...array_keys(self::all()), 'settings']);
    }
}
