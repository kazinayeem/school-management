import { OfficePage } from '@/components/office-ui';
import { ProfileForm, type ProfileValues } from '@/components/portal-profile';

export default function Profile({ navigation, roleLabel, profile }: { navigation: any; roleLabel: string; profile: ProfileValues }) {
    return (
        <OfficePage navigation={navigation} roleLabel={roleLabel} title="My Profile" subtitle="Your contact details, emergency contact, photo and password." wide>
            <ProfileForm profile={profile} showClass={false} />
        </OfficePage>
    );
}
