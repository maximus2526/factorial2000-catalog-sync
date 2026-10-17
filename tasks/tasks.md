# tasks.md — фікс temp-файлів і кеш-флашів + порт хука ключових слів

Дата: 2026-10-16
Плагін: `factorial2000-catalog-sync` (робоча копія в `E:\LocalWP\woodmart-dev\...\plugins\`)
Гілка: `split-premium` · Статус: зроблено, **НЕ закомічено**

## Для чого це (проблема)

Масовий URL-імпорт лишав сотні файлів у `/tmp`:

1. Завантажений фід кешувався у transient (`f2000cs_import_xml_<session>`), а сам файл отримував випадкове ім'я через `wp_tempnam( 'f2000cs_import_' )`.
2. Плагін сам викликав **глобальний** `wp_cache_flush()`:
   - `f2000cs_cleanup_wc_transients()` (`includes/functions.php`);
   - `cleanup_resources()` і ділянка «кожні 10 батчів» у `includes/class-stock-updater.php`;
   - фоновий `f2000cs_single_update_event`.
3. Після флашу transient зникав → кожен батч-запит качав фід **заново у новий** `wp_tempnam()`-файл, а попередні ніхто не видаляв (посилання губилося разом із transient-ом).
4. Глобальний флаш зносив не лише транзієнти, а весь object cache — зокрема власний кеш імпорт-сесії, тобто плагін сам штовхав себе на повторне завантаження.

Наслідок: `/tmp` забивається, імпорт перекачує фід на кожному кроці, resume-сесія втрачається.

## Що зроблено

### 1) Детермінований файл на сесію — `admin/page-import.php`

- `f2000cs_import_xml_temp_path()` — `get_temp_dir() . 'f2000cs_import_' . sanitize_key( $session ) . '.tmp'`.
- `f2000cs_resolve_import_xml_source()` — сесія генерується **до** завантаження; файл переюзається через `file_exists()` (transient більше не джерело істини); `touch()` освіжає mtime, щоб активна сесія не попала під прибиральник; невдалий запис видаляє частковий файл.
- `f2000cs_cleanup_import_xml_session()` — видаляє і детермінований шлях, навіть якщо transient уже зник.
- `f2000cs_get_pending_import()` — знаходить сесію і без transient (fallback на детермінований шлях).

### 2) Точковий флаш кешу

- `includes/functions.php` — `f2000cs_flush_transients_cache()`: `function_exists( 'wp_cache_flush_group' ) ? wp_cache_flush_group( 'transients' ) : нічого`. На WP < 6.1 свідомо **нічого** — глобальний флаш і був причиною проблеми (плагін тримає `Requires at least: 5.8`).
- `f2000cs_cleanup_wc_transients()` тепер викликає хелпер.
- `includes/class-stock-updater.php` — `cleanup_resources()` і «кожні 10 батчів» → точковий флаш.
- Тест-скан `TempFilesCleanupTest::test_production_code_has_no_global_cache_flush_calls`: у продакшн-коді не лишилось жодного `wp_cache_flush()`.

### 3) Прибиральник старих tmp — `includes/uninstall-cleanup.php`

- `f2000cs_cleanup_stale_temp_files( $max_age = 2 * HOUR_IN_SECONDS )` — `glob( get_temp_dir() . 'f2000cs_*.tmp' )`, видаляє старші за 2 год; `$max_age = 0` → видалити всі.
- **Cron**: подія `f2000cs_temp_cleanup_cron` (`daily`) → той самий скан.
- **Адмінка**: `f2000cs_maybe_cleanup_stale_temp_files` на `admin_init` — не частіше разу на годину (throttle-transient) + гарантує наявність cron-події.
- **Uninstall**: чистить `f2000cs_temp_cleanup_cron` і всі `f2000cs_*.tmp` будь-якого віку.

### 4) `wp_tempnam()` — кожен виклик видаляється у `finally`

- Перевірено і покрито тестами 3 місця з видаленням у `finally`: `class-stock-updater.php` (`f2000cs_curr_` + fallback-ділянка) і `class-xml-parser.php` (`load_currencies_from_xml`).
- `page-import.php`: виклик `wp_tempnam` прибрано разом із random-шляхом.
- `tests/includes/wp-stubs.php`: стуб `wp_tempnam` тепер міррорить ядро (`<name>-<random>.tmp`) — старий `tempnam()` на Windows обрізав префікс до «f20», і тести не побачили б витоків.

### 5) Порт хука ключових слів — `includes/parsers/class-fields-updater.php`

- `update_tags()`: `$by_lang = array_filter( [ 'ru' => keywords, 'uk' => keywords_ua ] )` + `do_action( 'f2000cs_offer_keywords', $product_id, $by_lang )`. Портовано 1:1 з копії `factorial2000-catalog-sync-for-promua` (файли тепер ідентичні).
- Це єдина точка інтеграції для `factorial2000-translator` (споживач: `includes/class-prom-keywords.php`, `add_action( 'f2000cs_offer_keywords', …, 10, 2 )`).
- Увага: спрацьовує лише коли в налаштуваннях імпорту увімкнено поле «tags» (як і в for-promua).

### 6) Runtime-флаш памʼяті — `includes/functions.php`, `includes/class-stock-updater.php`

- Знахідка ревю: глобальний `wp_cache_flush()` був не лише шкідливий для сесії, а й «клапаном» памʼяті довгих прогонів (пари з `gc_collect_cycles()`). Точковий флаш групи `transients` його не замінює: `posts` / `post_meta` / `products` накопичуються до кінця запиту (ризик на дуже великих каталогах).
- Новий хелпер `f2000cs_flush_runtime_cache()`: `wp_cache_flush_runtime()` (WP 6.0+) — звільняє лише in-process кеш, persistent не чіпає; на старіших WP свідомо no-op (як і з групами).
- Викликається у двох періодичних місцях: `cleanup_resources()` (finally кожного прогону) і «кожні 10 батчів».
- Тести: `StockUpdaterTest` (+2) — `cleanup_resources` → рівно 1 runtime-флаш і 0 глобальних; повний прогін 1-батчевого фіда → рівно 2 (батч + cleanup). Стуб `wp_cache_flush_runtime` + лічильник `cache_runtime_flush_calls` у `F2000CS_Test_State`.

### 7) Мови перекладача в Prom-фіді — `includes/marketplace/class-prom-feed.php`, `class-marketplace-wc-source.php`

- Коли активний `factorial2000-translator` і в ньому увімкнені **ru+uk**, фід ділить поля: `name`/`description` → російський текст, `name_ua`/`description_ua` → український. Немає російського перекладу → фолбек на основну мову (як читає сам перекладач). Без перекладача або без обох мов — значення як було.
- `Prom_Feed::split_languages()` — мʼяка перевірка `class_exists()` для `F2000TR\Strings`/`Languages`, `get_languages()` має містити і ru, і uk; читання через `F2000TR\WooCommerce::translate_display()` (групи), фолбек на `Strings::translate()`: тексти поза тегами (суфікси варіацій « - S») зберігаються.
- **Сире імʼя для фіда**: `Marketplace_WC_Source::raw_product_name()` — на час читання `get_name()` знімаються лише колбеки перекладача (`F2000TR\WooCommerce::translate_text` на 5 хуках: `get_name`/`variation_get_name`/`product_title`/`attribute_label`/`variation_option_name`) і повертаються у `finally`. Без цього перекладач згортав імʼя до поточної мови ще до фіда (у живому тесті `name_ua` виходив російським).
- Опис ріжеться по мовах **до** нормалізації: `pick_raw_description()` (новий) + `split_languages()` → `normalize_description_html()` на кожну мову; фолбек-опис збирається на кожну мову зі своєї назви (`fallback_description_html()`). `pick_description_html()` лишено обгорткою (BC).
- Бонус: перевірка «назва без літер» тепер на вибраному (головному) значенні — теги `[:ru]` більше не проходять її своїми літерами.
- Тести: `MarketplacePromFeedTest` (+5) — розкладка ru/uk, фолбек без перекладу, «не ru+uk» не чіпає значення, тегований цифровий нейм скіпається, суфікс варіації переживає розкладку; `MarketplaceWcSourceTest` (+1) — фільтр перекладача знімається на читанні й повертається. Стаби `F2000TR\Languages`/`Strings`/`WooCommerce` — `tests/includes/f2000tr-stubs.php` + стан `f2000tr_languages`/`f2000tr_default_language` у `F2000CS_Test_State`.
- Живий чек (Playwright, woodmart-dev, 2026-10-17): товар 123799 з ru-перекладом → у фіді `name`=«Защитные наколенники…», `name_ua`=«Захисні наколінники…», обидва описи на своїх мовах, теги не течуть. Перед чеком виправлено загажене тегування товару в БД (`[:en]`→`[:uk]`, спадок давніх експериментів).

## Як тепер працює URL-імпорт (коротко)

1. **Перший запит**: сесія → шлях `/tmp/f2000cs_import_<session>.tmp` не існує → завантажити фід → записати → `set_transient` (довідково, більше не критично).
2. **Наступні запити**: шлях детермінований → `file_exists` → reuse + `touch`, жодного повторного завантаження. Якщо transient зник — reuse все одно працює.
3. **Якщо файл загубився** (ребут, чистий tmp) — перекачує: URL приходить із форми або з resume-контексту.
4. **Прибирання**: кінець сесії (`f2000cs_cleanup_import_xml_session`), скан >2 год (cron + адмінка), uninstall.

## Докази і як перевірити

- PHPUnit: **415 tests / 15433 assertions, OK** (1 pre-existing skip):
  `php vendor/phpunit/phpunit/phpunit`
- PHPCS (WordPress + PHPCompatibilityWP): **exit 0**, без помилок і попереджень:
  `php vendor/squizlabs/php_codesniffer/bin/phpcs`
- Після прогонів тестів у `%TEMP%` — **0 файлів** `f2000cs_*`.
- Нові/розширені тести:
  - `tests/unit/ImportSessionFileTest.php` (5) — детермінований шлях, reuse без transient, touch, cleanup сесії, pending import;
  - `tests/unit/TempFilesCleanupTest.php` (6) — скан за віком, cron, адмін-throttle + планування, uninstall (cron + файли), заборона глобального флаша в джерелах;
  - `FieldsUpdaterTest` (+4) — хук ключових слів (3) + temp-файл `load_currencies`;
  - `StockUpdaterTest` (+2) — битий фід → 0 temp-файлів; `cleanup_resources` → лише група `transients`;
  - `FunctionsTest` (+1) — `f2000cs_cleanup_wc_transients` флашить лише групу;
  - Стуби: `get_temp_dir`, `wp_cache_flush_group`, лічильники флашів у `F2000CS_Test_State`.

## Повний ручний E2E (2026-10-17, Playwright на woodmart-dev + CLI/SQL)

Матриця (усі — вручну в браузері, стан звірено в БД):

- **Оновлення XML**: синхронний прогін ✓; фоновий (черга → cron-спавн → виконано, черга очищена, без помилок у логах) ✓; «Зупинити cron» → «Неактивне»; автооновлення повертає наступний запуск (стоп навмисно «залипає» — є тест) ✓; відсутні у фіді товари автоматично драфтяться з маркером `_f2000cs_auto_drafted` ✓; повернені у фід товари **republish-яться** з очисткою маркера ✓.
- **Імпорт XML**: URL ✓ (100%, товар створено); файл (upload) ✓; stop/resume ✓ — після зупинки фід **видалено з uploads (URL 404)**, resume все одно добіг до 100% ⇒ сесійний tmp переюзано, повторного завантаження немає; варіативний: analyze (1 група, 2 варіації, атрибут авто-вибраний) + «Імпортувати з вибраними атрибутами» ✓ (батько + 2 варіації, publish, фото сайдлоаднуто).
- **Вигрузка**: фільтр XML — «Видалено 5 товарів», залишилось 6 (S1–G2 — вони в trash, тобто коректно вважаються новими) ✓; редактор XML — дерево («E2E 11»), вибір категорії (11), «Сформувати XML» → валідний файл з 6 офферами ✓.
- **Маркетплейси**: збір/публікація + guard ✓ (раніше); мови ru/uk у фіді ✓ (раніше).
- **Фронтенд**: футер vendor code для адміна («Vendor Code: LIVED_E2E9001» + кнопка копіювання) ✓; товарні сторінки uk/ru ✓ (раніше).
- **Налаштування**: слот постачальника (URL/префікс) зберігся ✓; інтервал перемикався ✓; cron-подія sweep-а в розкладі ✓.
- **Сторінки**: Документація (27k, без PHP-нотисів) ✓; Акаунт (Freemius, без PHP-нотисів; єдина консольна помилка — штатний API-виклик SDK «no newer release»).
- **Sweep старих tmp**: живий CLI-прогін (removed=1, свіжий збережено) ✓; тротл і cron ✓ (деталі — нижче).

## Живий E2E решти функцій (2026-10-17, Playwright + CLI)

- **Імпорт за URL** (`LIVED_` префікс, 2 оффери): 100% завершено, створився товар 129559 (SKU LIVED_E2E9001). FPM-нюанс: Local-ів `sys_temp_dir=C:\Windows\TEMP`, а цей каталог стандартному користувачу **не можна лістити** (ACL) — тому glob там завжди порожній (не баг плагіна; на Linux-хостингу /tmp ліститься, а сесійний файл видаляється і без лістингу — прямим шляхом).
- **Оновлення окремих полів + хук `f2000cs_offer_keywords`**: tags-only прогін → «Оновлено: 2, не знайдено: 0»; у БД `_f2000tr_prom_keywords` = `[:uk]наколінники тест[:ru]наколенники тест[:]` (ru/uk по своїх слотах).
- **Оновлення XML (сток)**: постачальника налаштовано на локальний фід, «Запустити оновлення» (синхронно) → `f2000cs_free_update_count=1`, `day=2026-10-17` (запис робиться лише після фактичного прогону); усі 11 LIVED_-товарів були у фіді — стани не змінились (нейтрально).
- **Sweep старих tmp**: перевірено прогоном на живому WP через CLI (`f2000cs_cleanup_stale_temp_files()`): `removed=1`, свіжий файл збережено; cron-подія `f2000cs_temp_cleanup_cron` у розкладі; тротл admin_init (`f2000cs_temp_sweep_done`) спрацьовує. Новий юніт-тест `test_sweep_handles_a_native_windows_temp_path` — нативні бекслеші в шляху теж чистяться (glob на PHP 8.5 їх переносить; RED не отримано — зафіксовано як characterization).
- Стан дев-сайту після тестів: створено LIVED_E2E9001; в uploads лежить `e2e-test-feed.xml`; слот-1 постачальника вказує на нього; у 123799 з'явилися `_f2000tr_prom_keywords`; ru увімкнено в перекладачі; Prom-фід перепубліковано.

## Стан

- 2026-10-17: **реліз 0.7.0** — усе закомічено в `split-premium`, merge у `main`, тег `v0.7.0`, GitHub Actions: Release-збірка + деплой на Freemius (plugin 36366).
- `readme.txt`: записи в changelog 0.7.0 (секції Improved і Fixed).
