# Factorial2000 Catalog Sync — Агентський скіл

> Остання актуальна версія: **0.6.3**
> Тестове покриття: **162 тести / 820 асертів**, PHPCS чистий у всіх файлах.

Цей документ — **обов'язковий до прочитання** перед будь-якими змінами в репозиторії.
Порушення конвенцій, описаних тут, зламають тести, білд і сумісність з wp.org.

---

## 1. Про проект

**WordPress-плагін** для імпорту товарів із Prom.ua XML/YML, синхронізації залишків і цін, фільтрації вигрузок та Telegram-сповіщень.

| Параметр | Значення |
|----------|----------|
| Тип | `wordpress-plugin` |
| PHP | 7.4+ (typed properties у парсерах) |
| WP | 5.8+, WooCommerce 3.0+ |
| Namespace | `F2000CS\` — **усі класи** |
| Префікс функцій | `f2000cs_` — **усі глобальні функції** |
| Префікс хуків | `f2000cs_` |
| Префікс опцій | `f2000cs_` |
| Text domain | `factorial2000-catalog-sync` |
| CSS-префікс | `.f2000cs-*` (BEM) |
| JS-простір | `f2000cs*` |

---

## 2. Файлова структура

```
factorial2000-catalog-sync/
├── factorial2000-catalog-sync.php   — ВХІДНА ТОЧКА: defines, requires, hooks
├── includes/uninstall-cleanup.php   — Чисте видалення через Freemius after_uninstall
├── readme.txt                       — wp.org readme (Stable tag + changelog)
│
├── includes/                        — Бізнес-логіка (тестовано офлайн)
│   ├── functions.php                — Хелпери: слоти постачальників, price-adjust, Telegram, фоновий sync
│   ├── class-cron-job.php           — Cron_Job: розклад + update_stock()
│   ├── class-licensing.php          — Freemius + Free/Pro гейти + захист опцій
│   ├── class-stock-updater.php      — XML_Stock_Updater: оновлення стоку/цін з фіду (cron)
│   ├── class-xml-export-filter.php  — XML_Export_Filter: вилучення існуючих товарів із XML
│   ├── class-xml-editor.php         — XML_Editor: фільтрація за категоріями/оферами + умови
│   ├── class-image-processor.php    — Image_Processor (Pro): resize/convert/optimize зображень
│   ├── class-frontend-display.php   — Frontend_Display: vendor code у футері для адмінів
│   └── parsers/
│       ├── class-xml-parser.php     — XML_Parser: повний імпорт (прості + варіативні продукти)
│       └── class-fields-updater.php — Fields_Updater: оновлення окремих полів товарів
│
├── admin/                           — Інтерфейс адмінки (тестується через AdminSmokeTest + AdminSourceTest)
│   ├── admin-menu.php               — Реєстрація меню
│   ├── admin-assets.php             — Enqueue CSS/JS на сторінки плагіна
│   ├── settings-page.php            — Завантажувач: require решти модулів
│   ├── settings-fields.php          — Settings API: поля + панелі постачальників
│   ├── page-update.php              — Сторінка «Оновлення XML»
│   ├── page-import.php              — Сторінка «Імпорт XML» + AJAX
│   ├── page-export.php              — Сторінка «Налаштування вигрузки» + фільтр
│   └── xml-editor.php              — Модуль «Редактор вигрузок XML»: картка + AJAX
│
├── assets/
│   ├── css/admin-settings.css       — Всі стилі адмінки (BEM)
│   └── js/
│       ├── admin-settings.js        — Постачальники, Pro-блокування, слайдери, trial countdown
│       ├── admin-import.js          — Імпорт/аналіз/update-fields
│       ├── admin-xml-editor.js      — Редактор вигрузок (3-колонковий UI)
│       └── admin-support.js         — Віджет підтримки
│
├── freemius/                        — SDK (НЕ РЕДАГУВАТИ)
│
├── tests/                           — PHPUnit-набір (запускається офлайн)
│   ├── bootstrap.php                — Завантажує includes/ + стаби
│   ├── phpunit.xml.dist
│   ├── includes/
│   │   ├── wp-stubs.php             — Стаби WP-функцій (options, cron, HTTP, hooks, $wpdb)
│   │   ├── BaseTestCase.php         — Базовий клас: reset + capture()
│   │   └── reflection.php           — Доступ до private/protected через Reflection
│   └── unit/
│       ├── PluginMetaTest           — Консистентність версій, заголовки, namespace/guard scan
│       ├── FunctionsTest            — Слоти, Telegram, фоновий sync
│       ├── CronJobTest              — Розклад, activate/deactivate, reschedule
│       ├── StockUpdaterTest         — price-adjust, memory-ліміти, повний цикл з XML-фікстурою
│       ├── XmlParserTest            — Транслітерація, офери, категорії, атрибути
│       ├── FieldsUpdaterTest        — ALLOWED_FIELDS, диспетчер оновлень
│       └── XmlExportFilterTest      — SKU/group-id/price фільтрація (чистий SimpleXML)
│       ├── LicensingTest            — Free/Pro гейти, trial, квоти, захист опцій
│       ├── FreemiusUkI18nTest       — Ключі та повнота української мапи Freemius
│       ├── ExportPrefixWiringTest   — Використання правильного ключа SKU-префікса
│       ├── FrontendDisplayTest      — init() без WooCommerce — no-op
│       ├── XmlEditorTest            — Категорії, офери, умови, генерація XML
│       ├── ImageProcessorTest       — Pro-обробка зображень
│       ├── AdminSourceTest          — Source-level: AJAX nonce, cap, settings keys, menu
│       └── AdminSmokeTest           — Виконання: рендер сторінок, реєстрація опцій, enqueue
│
├── composer.json                    — dev: PHPUnit 12, PHPCS, WPCS, PHPCompatibility
├── package.json                     — Gulp + inquirer + archiver + semver
├── gulpfile.js                      — Меню білда
├── .phpcs.xml.dist                  — WordPress standard, виключає tests/ і freemius/
└── SKILL.md                         — Цей файл
```

---

## 3. Архітектура та потоки даних

### 3.1 Система слотів постачальників

Слот 1 використовує **застарілий ключ URL**: `f2000cs_url` (без `_1`).
Усі інші ключі слота 1 — **з суфіксом**: `f2000cs_sku_prefix_1`, `f2000cs_skip_price_1`, тощо.
Слоти 2+ використовують `f2000cs_url_N`, `f2000cs_sku_prefix_N`.

> **Критично:** НІКОЛИ не читай `f2000cs_sku_prefix` (без суфікса). Це застарілий ключ, який більше не записується. Використовуй `f2000cs_sku_prefix_1`.

### 3.2 Free vs Pro

- `f2000cs_is_pro()` → `true` коли: оплачена ліцензія Freemius **АБО** Freemius trial (14 днів, без карти) **АБО** `F2000CS_FORCE_PRO` / фільтр `f2000cs_is_pro`.
- Free: 1 постачальник (+grandfathered), 1 оновлення/добу, без price adjust, без quantity, без variable-low-instock rule.
- Pro-гейти: і фізичні (`f2000cs_register_pro_option_guards` + `pre_update_option` фільтри), і UI (`disabled` + бейджі).
- Trial = Pro за функціоналом.

### 3.3 Інструменти роботи з XML (5 різних)

| Клас | Призначення | Де використовується |
|------|------------|-------------------|
| `XML_Parser` | **Імпорт** (створює WC-продукти) | page-import AJAX |
| `XML_Stock_Updater` | **Синхронізація стоку/цін** за SKU | Cron + page-update |
| `Fields_Updater` | **Оновлення окремих полів** існуючих товарів | page-import «Оновити поля» |
| `XML_Export_Filter` | **Вилучення існуючих** товарів із XML | page-export форма |
| `XML_Editor` | **Вибіркова фільтрація** з умовами | page-export редактор |

### 3.4 Cron-потік

`f2000cs_update_stock_cron` → `Cron_Job::update_stock()` → перебирає активні URL → `XML_Stock_Updater` на кожен → `f2000cs_after_stock_update_complete()` (low-instock правило + Telegram).
Фоновий sync: `f2000cs_single_update_event` (one-shot) + `f2000cs_bg_batch_remaining` transient → пост-обробка після останнього завдання.

### 3.5 JSON/id типи даних

**Категорії та офери в XML мають числові рядкові id** (напр. `id="11"`). У PHP ключі масивів з числових рядків автоматично стають int. Тому:
- При побудові JSON ВСІ id мають явно каститися до `(string) $id`
- У JS селекторах використовуй `.attr('data-f2000cs-id')`, **не** `.data()` — jQuery `.data()` конвертує `"11"` у число `11`
- У `in_array()` використовуй строге порівняння `true`

---

## 4. Конвенції коду

### 4.1 Обов'язкові (порушення = phpcs error / баг)

- **Кожен файл** починається з `defined( 'ABSPATH' ) || exit;`
- **Кожен клас** у `namespace F2000CS;`
- **Усі рядки** через `__()` / `esc_html__()` з text domain `factorial2000-catalog-sync`
- **Кожен AJAX/admin-post хендлер** перевіряє nonce (`wp_verify_nonce`/`check_ajax_referer`) + `current_user_can('manage_options')`
- **Увесь вивід** екранується (`esc_html`, `esc_attr`, `esc_url`, `esc_html_e`, `wp_kses_post`)
- **При зміні версії** оновлюй три місця разом: заголовок `Version:`, `F2000CS_VERSION`, `Stable tag` у readme.txt
- **При додаванні нового includes/ файлу** додавай `require_once` у дві точки: `factorial2000-catalog-sync.php` і `tests/bootstrap.php`
- **Не редагуй `freemius/`** — це сторонній SDK
- **Admin-файли** використовують `require_once` у `admin/settings-page.php` (завантажувач)

### 4.2 Приклади

```php
// ✅ Правильно — клас у namespace
namespace F2000CS;
class My_Class { ... }

// ✅ Правильно — функція з префіксом
function f2000cs_do_something() { ... }

// ✅ Правильно — nonce + cap у AJAX
function f2000cs_handle_ajax() {
    check_ajax_referer( 'f2000cs_my_action', 'nonce' );
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_send_json_error( ... );
    }
    // ...
}

// ✅ Правильно — читання SKU-префіксу слота 1
$prefix = get_option( 'f2000cs_sku_prefix_1', '' );

// ❌ НЕПРАВИЛЬНО — застарілий ключ
$prefix = get_option( 'f2000cs_sku_prefix', 'NEW_' );
```

---

## 5. Тестування

### 5.1 Запуск

```bash
composer test          # PHPUnit (офлайн, без WP)
composer phpcs         # WordPress coding standards
composer phcbf         # Авто-фікс
npx gulp phpcs         # Те саме через gulp
npx gulp phpunit       # Те саме через gulp
```

### 5.2 Як працює тест-харнес

`tests/bootstrap.php` → завантажує стаби WP-функцій (`tests/includes/wp-stubs.php`) → завантажує `includes/*.php` → тести запускаються **без WordPress, WooCommerce чи БД**.

Стаб-функції зберігають стан у `F2000CS_Test_State`:
- `$options` / `$transients` — in-memory key-value
- `$cron_events` — список подій
- `$hooks` — зареєстровані хуки (НЕ очищуються при reset)
- `$http_get_responses` — програмовані HTTP-відповіді (ключ = URL)
- `$http_post_queue` — черга відповідей для `wp_remote_post`
- `$http_posts` — журнал надісланих POST-запитів
- `$menu_pages` / `$registered_settings` / `$enqueued_scripts` — стан адмінки

`$GLOBALS['wpdb']` — `F2000CS_Fake_WPDB` з чергами `$col_queue` / `$results_queue`.

Базовий клас `F2000CS_Unit_TestCase` (у `tests/includes/BaseTestCase.php`) викликає `F2000CS_Test_State::reset()` перед кожним тестом і має `capture()` для буферизації виводу.

### 5.3 Як писати тести

```php
final class F2000CS_Unit_MyThingTest extends F2000CS_Unit_TestCase {
    public function test_my_feature(): void {
        // 1. Налаштувати стан
        update_option( 'f2000cs_url', 'http://example.com/feed.xml' );

        // 2. Викликати код
        $result = some_function();

        // 3. Перевірити
        $this->assertSame( 'expected', $result );
    }
}
```

**Тестування private/protected методів:**
```php
F2000CS_Test_Reflection::invoke( $object, 'method_name', array( $arg1, $arg2 ) );
F2000CS_Test_Reflection::get( $object, 'property_name' );
F2000CS_Test_Reflection::set( $object, 'property_name', $value );
```

**Тестування HTTP-залежних шляхів:**
```php
F2000CS_Test_State::$http_get_responses['http://example.com/feed.xml'] = array(
    'response' => array( 'code' => 200 ),
    'body'     => '<xml>...</xml>',
);

// Перший виклик wp_remote_get поверне відповідь вище
```

**Source-level тести (без виконання):**
```php
$source = (string) file_get_contents( dirname( __DIR__, 2 ) . '/admin/my-file.php' );
$this->assertStringContainsString( "wp_verify_nonce(...)", $source );
```

### 5.4 Що НЕ тестується офлайн (і чому)
- **AJAX-хендлери** — `wp_send_json` у цій версії WP не перехоплюється через `wp_die_handler`; логіка покрита юніт-тестами класів.
- **Меню адмінки** — `f2000cs_add_admin_menu()` перевіряє `class_exists('WooCommerce')`, якого немає в офлайн-тестах; структура меню перевіряється через AdminSourceTest.
- **Повна інтеграція з WooCommerce** — потребує справжнього WP (є окремим follow-up).

---

## 6. Білд-система

```bash
npx gulp                  # Інтерактивне меню
npx gulp release          # → release/<slug>/ + .zip + svn/trunk + svn/tags/<ver>
npx gulp version:up --level=patch|minor|major
npx gulp version:down     # patch -1
npx gulp version:set --version=1.2.3
npx gulp phpcs
npx gulp phpunit
npx gulp full             # phpcs → phpunit → +patch → release
```

**Release-білд створює:**
1. `release/factorial2000-catalog-sync/` — чиста папка плагіна (без `.git`, `tests/`, `node_modules/`, `vendor/`, `gulpfile.js`, інших dev-файлів)
2. `release/factorial2000-catalog-sync-<ver>.zip`
3. `release/svn/{trunk,tags/<ver>,assets}/` — SVN-розкладка з README

### CI (GitHub Actions)

- **`.github/workflows/test.yml`** — запускається на push/PR: PHPUnit + PHPCS + gulp phpcs/phpunit на PHP 7.4 і 8.2.
- **`.github/workflows/release.yml`** — push тега `v*` (або manual `workflow_dispatch`): PHPUnit → `gulp release` → GitHub Release zip → деплой на Freemius через `bin/freemius-deploy.php` (падає при помилці API). За замовчуванням Freemius статус = `pending`.

**GitHub Secrets** (Settings → Secrets and variables → Actions) — з Freemius **Product → Settings → Keys** (plugin scope):

| Secret | Що це |
|--------|--------|
| `FREEMIUS_PUBLIC_KEY` | Product public key `pk_…` |
| `FREEMIUS_SECRET_KEY` | Product secret key `sk_…` (обов’язково) |

Опційно: `FREEMIUS_API_SCOPE=developer` + `FREEMIUS_DEV_ID` + developer keys з My Profile.  
Лише `pk_` з SDK-сніпета без `sk_` — деплой не запрацює. Plugin ID `36366` зашитий у workflow.

**Реліз-флоу:**
1. `npx gulp version:up --level=patch` — бамп версії
2. `git add -A && git commit -m "Release X.Y.Z"`
3. `git tag vX.Y.Z && git push origin main --tags`
4. Actions: GitHub Release + upload на Freemius; за потреби зміни статус на Released у Freemius

**Оновлення версії** змінює три місця **одночасно**:
1. `* Version: X.Y.Z` — заголовок плагіна
2. `define( 'F2000CS_VERSION', 'X.Y.Z' )`
3. `Stable tag: X.Y.Z` у readme.txt

---

## 7. Поширені пастки

1. **`jQuery .data()` конвертує числові рядки в числа.** У редакторі XML атрибут `data-f2000cs-id="11"` → `.data()` поверне `11` (number). Для селекторів і пошуку використовуй `.attr('data-f2000cs-id')` для збереження рядкового типу.

2. **WP `.notice` + `hidden` атрибут:** авторський CSS перебиває UA-правило `[hidden]{display:none}`. У редакторі є явне `[hidden]{display:none!important}`.

3. **`$plugin` — зарезервована змінна WP.** У `wp-settings.php` використовується як змінна циклу. Ніколи не називай свою змінну `$plugin` — використовуй `$f2000cs_plugin_dir`.

4. **XML-теги чутливі до регістру.** `XML_Editor` і `XML_Parser` тепер шукають елементи/атрибути case-insensitively (з нормалізацією `_`/`-`). При додаванні нового парсингу використовуй `$this->child()` / `$this->attribute()`.

5. **PHP ключі масивів із числових рядків стають int.** `$arr['11']` → `$arr[11]`. При побудові JSON для категорій/оферів завжди касти id до рядка.

6. **Gulp version bump змінює файли.** Після bump завжди перевіряй `PluginMetaTest`.

---

## 8. Як додати нову фічу

1. Створи клас/метод у `includes/` (або `includes/parsers/`)
2. Додай `require_once` у `factorial2000-catalog-sync.php` і `tests/bootstrap.php`
3. Напиши тести в `tests/unit/` — хоча б source-level smoke
4. Налаштуй admin UI у відповідному `admin/page-*.php` + AJAX
5. Додай рядки перекладу (`__()`) і онови `.pot` за потреби
6. `composer test && composer phpcs && npx gulp full` — **все має бути зеленим**
7. Якщо змінювався `readme.txt` — додай запис у changelog

---

## 9. Як фіксити баг

1. **Спочатку тест, що відтворює баг** (юніт або source-level)
2. Виправ код
3. `composer test` — тест має проходити, решта не ламатися
4. `composer phcbf` — авто-фікс стилю

---

## 10. Відомі проблеми (актуальний список)

- Stock sync лише через `XML_Stock_Updater` (legacy path у `XML_Parser` видалено)
- Дубль логіки перебудови XML між `XML_Export_Filter` і `XML_Editor` (`copy_element_data`)
- `settings-fields.php` — 33KB, можна розбити далі
- Немає інтеграційних тестів з реальним WP — smoke-скрипт є, але не в CI
- 35+ некомітених файлів — увесь 0.5.x, редактор, тести, білд
- Редактор вантажить весь XML у пам'ять — для гігантських фідів межа

---

## 11. Чек-лист перед комітом

- [ ] `composer test` зелений
- [ ] `composer phpcs` чистий
- [ ] `npx gulp phpcs && npx gulp phpunit` зелені
- [ ] `php -l` на кожному новому PHP-файлі
- [ ] `node --check` на кожному новому JS-файлі
- [ ] Усі нові файли мають `defined( 'ABSPATH' ) || exit;`
- [ ] Усі нові класи в `F2000CS\` namespace
- [ ] Усі нові функції з `f2000cs_` префіксом
- [ ] Усі рядки через `__()` з `factorial2000-catalog-sync`
- [ ] Nonce + cap у кожному AJAX-хендлері
- [ ] Вивід екранується
- [ ] Немає `f2000cs_sku_prefix` без суфікса (крім застарілих)
- [ ] `PluginMetaTest::test_version_is_consistent_across_files` проходить
