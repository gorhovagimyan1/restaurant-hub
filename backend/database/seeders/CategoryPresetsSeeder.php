<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\CategoryPreset;
use Illuminate\Database\Seeder;

/**
 * The starter category catalog, in every language, and links existing
 * restaurant categories to it by name. Safe to re-run:
 *
 *     php artisan db:seed --class=CategoryPresetsSeeder
 *
 * Options are matched by English name; ones a super-admin has since edited
 * keep their edits.
 */
class CategoryPresetsSeeder extends Seeder
{
    /** [en, ru, hy] => each [name, description] */
    private const PRESETS = [
        [['Breakfast', 'Served every morning.'], ['Завтраки', 'Подаются каждое утро.'], ['Նախաճաշ', 'Մատուցվում է ամեն առավոտ։']],
        [['Starters', 'Small plates to begin your meal.'], ['Закуски', 'Небольшие блюда для начала трапезы.'], ['Նախուտեստներ', 'Փոքր ուտեստներ՝ ճաշը սկսելու համար։']],
        [['Cold Appetizers', 'Cheeses, cold cuts and pickles.'], ['Холодные закуски', 'Сыры, мясная нарезка и соленья.'], ['Սառը նախուտեստներ', 'Պանիրներ, մսային կտրատվածք և թթուներ։']],
        [['Hot Appetizers', 'Warm bites to share.'], ['Горячие закуски', 'Тёплые закуски на компанию.'], ['Տաք նախուտեստներ', 'Տաք ուտեստներ ընկերների հետ կիսելու համար։']],
        [['Soups', 'Warm and hearty.'], ['Супы', 'Тёплые и сытные.'], ['Ապուրներ', 'Տաք և սննդարար։']],
        [['Salads', 'Fresh, crisp and colorful.'], ['Салаты', 'Свежие, хрустящие и яркие.'], ['Աղցաններ', 'Թարմ, խրթխրթան և գունեղ։']],
        [['Main Courses', 'Chef signatures and hearty plates.'], ['Основные блюда', 'Фирменные блюда шефа и сытные порции.'], ['Հիմնական ուտեստներ', 'Խոհարարի ստորագրային և սննդարար ուտեստներ։']],
        [['Armenian Cuisine', 'Traditional Armenian dishes.'], ['Армянская кухня', 'Традиционные армянские блюда.'], ['Հայկական խոհանոց', 'Ավանդական հայկական ուտեստներ։']],
        [['Grill & BBQ', 'Khorovats and dishes from the grill.'], ['Гриль и шашлык', 'Хоровац и блюда на гриле.'], ['Խորոված', 'Խորոված և գրիլի ուտեստներ։']],
        [['Steaks', 'Prime cuts, cooked to order.'], ['Стейки', 'Отборные стейки нужной вам прожарки.'], ['Սթեյքեր', 'Ընտիր միս՝ ձեր նախընտրած եփվածությամբ։']],
        [['Fish', 'Fresh fish dishes.'], ['Рыба', 'Блюда из свежей рыбы.'], ['Ձուկ', 'Թարմ ձկան ուտեստներ։']],
        [['Seafood', 'Fresh seafood and coastal favourites.'], ['Морепродукты', 'Свежие морепродукты и любимые блюда побережья.'], ['Ծովամթերք', 'Թարմ ծովամթերք և ծովափնյա սիրված ուտեստներ։']],
        [['Pasta', 'Italian pasta dishes.'], ['Паста', 'Итальянская паста.'], ['Մակարոնեղեն', 'Իտալական մակարոնեղեն։']],
        [['Pizza', 'Wood-fired, thin crust.'], ['Пицца', 'Из дровяной печи, на тонком тесте.'], ['Պիցցա', 'Փայտի վառարանում, բարակ խմորով։']],
        [['Burgers', 'Served with fries.'], ['Бургеры', 'Подаются с картофелем фри.'], ['Բուրգերներ', 'Մատուցվում են ֆրիով։']],
        [['Sandwiches & Wraps', 'Quick bites to go.'], ['Сэндвичи и роллы', 'Быстрый перекус с собой.'], ['Սենդվիչներ և ռոլեր', 'Արագ խորտիկ՝ տանելու համար։']],
        [['Sushi', 'Rolls, nigiri and sashimi.'], ['Суши', 'Роллы, нигири и сашими.'], ['Սուշի', 'Ռոլեր, նիգիրի և սաշիմի։']],
        [['Side Dishes', 'The perfect companions.'], ['Гарниры', 'Идеальное дополнение к блюду.'], ['Խավարտներ', 'Կատարյալ լրացում ուտեստին։']],
        [['Bread & Bakery', 'Freshly baked every day.'], ['Хлеб и выпечка', 'Свежая выпечка каждый день.'], ['Հաց և թխվածք', 'Թարմ թխված ամեն օր։']],
        [['Kids Menu', 'Small portions for little guests.'], ['Детское меню', 'Небольшие порции для маленьких гостей.'], ['Մանկական մենյու', 'Փոքր բաժիններ փոքրիկ հյուրերի համար։']],
        [['Vegetarian', 'Meat-free dishes.'], ['Вегетарианское', 'Блюда без мяса.'], ['Բուսակերական', 'Առանց մսի ուտեստներ։']],
        [['Desserts', 'Something sweet to finish.'], ['Десерты', 'Что-нибудь сладкое на завершение.'], ['Աղանդեր', 'Քաղցր ավարտ ճաշի համար։']],
        [['Soft Drinks', 'Chilled and refreshing.'], ['Безалкогольные напитки', 'Охлаждённые и освежающие.'], ['Զովացուցիչ ըմպելիքներ', 'Սառը և թարմացնող։']],
        [['Fresh Juices & Smoothies', 'Squeezed and blended to order.'], ['Фреши и смузи', 'Готовим прямо при заказе.'], ['Թարմ հյութեր և սմուզիներ', 'Պատրաստվում են պատվերով։']],
        [['Hot Drinks', 'Coffee & tea.'], ['Горячие напитки', 'Кофе и чай.'], ['Տաք ըմպելիքներ', 'Սուրճ և թեյ։']],
        [['Coffee', 'Espresso drinks and Armenian coffee.'], ['Кофе', 'Кофе на основе эспрессо и армянский кофе.'], ['Սուրճ', 'Էսպրեսոյով ըմպելիքներ և հայկական սուրճ։']],
        [['Tea', 'Black, green and herbal teas.'], ['Чай', 'Чёрный, зелёный и травяной чай.'], ['Թեյ', 'Սև, կանաչ և բուսական թեյեր։']],
        [['Alcohol', 'Wine, beer and cocktails. Must be 18+.'], ['Алкоголь', 'Вино, пиво и коктейли. Только 18+.'], ['Ալկոհոլ', 'Գինի, գարեջուր և կոկտեյլներ։ Միայն 18+։']],
        [['Wine', 'Armenian and imported wines.'], ['Вино', 'Армянские и импортные вина.'], ['Գինի', 'Հայկական և ներմուծված գինիներ։']],
        [['Beer', 'Draft and bottled.'], ['Пиво', 'Разливное и бутылочное.'], ['Գարեջուր', 'Լցնովի և շշալցված։']],
        [['Cocktails', 'Classic and signature cocktails.'], ['Коктейли', 'Классические и авторские коктейли.'], ['Կոկտեյլներ', 'Դասական և հեղինակային կոկտեյլներ։']],
        [['Spirits', 'Brandy, whisky, vodka and more.'], ['Крепкие напитки', 'Коньяк, виски, водка и другое.'], ['Թունդ ըմպելիքներ', 'Կոնյակ, վիսկի, օղի և այլն։']],
    ];

    public function run(): void
    {
        $existing = CategoryPreset::all()->keyBy(fn (CategoryPreset $p) => mb_strtolower($p->translations['en']['name'] ?? ''));

        foreach (self::PRESETS as $i => [$en, $ru, $hy]) {
            if ($existing->has(mb_strtolower($en[0]))) {
                continue; // Keep whatever a super-admin has made of it.
            }

            $existing[mb_strtolower($en[0])] = CategoryPreset::create([
                'translations' => [
                    'en' => ['name' => $en[0], 'description' => $en[1]],
                    'ru' => ['name' => $ru[0], 'description' => $ru[1]],
                    'hy' => ['name' => $hy[0], 'description' => $hy[1]],
                ],
                'sort_order' => ($i + 1) * 10,
                ...self::drinkDefaults($en[0]),
            ]);
        }

        $this->linkExistingCategories($existing->values()->all());
    }

    /**
     * Where a catalog category is prepared and whether it is 18+ (same table
     * as the 2026_11_25 migration, which set them on existing presets).
     *
     * @return array{prep_station: string, is_alcoholic: bool}
     */
    private static function drinkDefaults(string $englishName): array
    {
        [$station, $alcoholic] = match (mb_strtolower($englishName)) {
            'soft drinks' => ['ready', false],
            'fresh juices & smoothies', 'hot drinks', 'coffee', 'tea' => ['bar', false],
            'alcohol', 'cocktails' => ['bar', true],
            'wine', 'beer', 'spirits' => ['ready', true],
            default => ['kitchen', false],
        };

        return ['prep_station' => $station, 'is_alcoholic' => $alcoholic];
    }

    /**
     * Point custom categories at the catalog option with the same name (in any
     * language), one per restaurant, so they become translated automatically.
     *
     * @param  array<int, CategoryPreset>  $presets
     */
    private function linkExistingCategories(array $presets): void
    {
        $byName = [];
        foreach ($presets as $preset) {
            foreach ($preset->translations as $fields) {
                $byName[mb_strtolower(trim($fields['name'] ?? ''))] = $preset;
            }
        }

        Category::withTrashed()->whereNull('category_preset_id')->get()
            ->each(function (Category $category) use ($byName) {
                $preset = $byName[mb_strtolower(trim($category->name))] ?? null;

                $taken = $preset && Category::withTrashed()
                    ->where('restaurant_id', $category->restaurant_id)
                    ->where('category_preset_id', $preset->id)
                    ->exists();

                if ($preset && ! $taken) {
                    $category->forceFill(['category_preset_id' => $preset->id])->saveQuietly();
                }
            });
    }
}
