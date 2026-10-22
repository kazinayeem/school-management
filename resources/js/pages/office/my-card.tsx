import { MyCard, type CardData } from '@/components/personal-card';
import { OfficePage } from '@/components/office-ui';

export default function MyCardPage({ navigation, roleLabel, card, userId }: { navigation: any; roleLabel: string; card: CardData; userId: number }) {
    return (
        <OfficePage navigation={navigation} roleLabel={roleLabel} title="My ID Card" subtitle="Change your photo and download your own ID card." wide>
            <MyCard card={card} userId={userId} />
        </OfficePage>
    );
}
