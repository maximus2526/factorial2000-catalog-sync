<?php
/**
 * Documentation admin page with sticky sidebar navigation.
 *
 * @package Factorial2000_Catalog_Sync
 */

defined( 'ABSPATH' ) || exit;

/**
 * Render Documentation admin page.
 *
 * @return void
 */
function f2000cs_docs_page() {
	?>
	<div class="wrap f2000cs-docs-page">
		<?php f2000cs_render_admin_page_title( __( 'Factorial2000 Catalog Sync – Документація', 'factorial2000-catalog-sync' ) ); ?>

		<div class="f2000cs-docs-layout">
			<nav class="f2000cs-docs-nav" id="f2000cs-docs-nav">
				<ul class="f2000cs-docs-nav__list">
					<li>
						<a href="#quickstart" class="f2000cs-docs-nav__item is-parent"><?php esc_html_e( 'Швидкий старт', 'factorial2000-catalog-sync' ); ?></a>
					</li>
					<li>
						<a href="#xml-schema" class="f2000cs-docs-nav__item is-parent"><?php esc_html_e( 'Схема XML', 'factorial2000-catalog-sync' ); ?></a>
						<ul class="f2000cs-docs-nav__sublist">
							<li><a href="#xml-structure" class="f2000cs-docs-nav__item"><?php esc_html_e( 'Структура фіду', 'factorial2000-catalog-sync' ); ?></a></li>
							<li><a href="#xml-offer" class="f2000cs-docs-nav__item"><?php esc_html_e( 'Поля offer', 'factorial2000-catalog-sync' ); ?></a></li>
							<li><a href="#xml-example" class="f2000cs-docs-nav__item"><?php esc_html_e( 'Приклад', 'factorial2000-catalog-sync' ); ?></a></li>
						</ul>
					</li>
					<li>
						<a href="#suppliers" class="f2000cs-docs-nav__item is-parent"><?php esc_html_e( 'Постачальники', 'factorial2000-catalog-sync' ); ?></a>
						<ul class="f2000cs-docs-nav__sublist">
							<li><a href="#suppliers-url" class="f2000cs-docs-nav__item"><?php esc_html_e( 'URL XML', 'factorial2000-catalog-sync' ); ?></a></li>
							<li><a href="#suppliers-prefix" class="f2000cs-docs-nav__item"><?php esc_html_e( 'SKU-префікс', 'factorial2000-catalog-sync' ); ?></a></li>
							<li><a href="#suppliers-flags" class="f2000cs-docs-nav__item"><?php esc_html_e( 'Ціна та кількість', 'factorial2000-catalog-sync' ); ?></a></li>
							<li><a href="#suppliers-price" class="f2000cs-docs-nav__item"><?php esc_html_e( 'Коригування ціни', 'factorial2000-catalog-sync' ); ?></a></li>
							<li><a href="#suppliers-slots" class="f2000cs-docs-nav__item"><?php esc_html_e( 'Слоти', 'factorial2000-catalog-sync' ); ?></a></li>
						</ul>
					</li>
					<li>
						<a href="#update" class="f2000cs-docs-nav__item is-parent"><?php esc_html_e( 'Оновлення XML', 'factorial2000-catalog-sync' ); ?></a>
						<ul class="f2000cs-docs-nav__sublist">
							<li><a href="#update-modes" class="f2000cs-docs-nav__item"><?php esc_html_e( 'Режими запуску', 'factorial2000-catalog-sync' ); ?></a></li>
							<li><a href="#update-interval" class="f2000cs-docs-nav__item"><?php esc_html_e( 'Інтервал', 'factorial2000-catalog-sync' ); ?></a></li>
							<li><a href="#update-prices" class="f2000cs-docs-nav__item"><?php esc_html_e( 'Що оновлюється', 'factorial2000-catalog-sync' ); ?></a></li>
							<li><a href="#update-missing" class="f2000cs-docs-nav__item"><?php esc_html_e( 'Зниклі з XML', 'factorial2000-catalog-sync' ); ?></a></li>
							<li><a href="#update-variable-low" class="f2000cs-docs-nav__item"><?php esc_html_e( 'Мала наявність варіацій', 'factorial2000-catalog-sync' ); ?></a></li>
							<li><a href="#update-vendor" class="f2000cs-docs-nav__item"><?php esc_html_e( 'Vendor Code на товарі', 'factorial2000-catalog-sync' ); ?></a></li>
						</ul>
					</li>
					<li>
						<a href="#import" class="f2000cs-docs-nav__item is-parent"><?php esc_html_e( 'Імпорт', 'factorial2000-catalog-sync' ); ?></a>
						<ul class="f2000cs-docs-nav__sublist">
							<li><a href="#import-sources" class="f2000cs-docs-nav__item"><?php esc_html_e( 'Джерела', 'factorial2000-catalog-sync' ); ?></a></li>
							<li><a href="#import-simple" class="f2000cs-docs-nav__item"><?php esc_html_e( 'Прості товари', 'factorial2000-catalog-sync' ); ?></a></li>
							<li><a href="#import-variable" class="f2000cs-docs-nav__item"><?php esc_html_e( 'Варіативні', 'factorial2000-catalog-sync' ); ?></a></li>
							<li><a href="#import-resume" class="f2000cs-docs-nav__item"><?php esc_html_e( 'Продовження', 'factorial2000-catalog-sync' ); ?></a></li>
							<li><a href="#import-images" class="f2000cs-docs-nav__item"><?php esc_html_e( 'Зображення при імпорті', 'factorial2000-catalog-sync' ); ?></a></li>
						</ul>
					</li>
					<li>
						<a href="#fields-update" class="f2000cs-docs-nav__item is-parent"><?php esc_html_e( 'Оновлення полів', 'factorial2000-catalog-sync' ); ?></a>
						<ul class="f2000cs-docs-nav__sublist">
							<li><a href="#fields-available" class="f2000cs-docs-nav__item"><?php esc_html_e( 'Доступні поля', 'factorial2000-catalog-sync' ); ?></a></li>
							<li><a href="#fields-images" class="f2000cs-docs-nav__item"><?php esc_html_e( 'Зображення', 'factorial2000-catalog-sync' ); ?></a></li>
						</ul>
					</li>
					<li>
						<a href="#export" class="f2000cs-docs-nav__item is-parent"><?php esc_html_e( 'Вигрузка XML', 'factorial2000-catalog-sync' ); ?></a>
						<ul class="f2000cs-docs-nav__sublist">
							<li><a href="#export-filter" class="f2000cs-docs-nav__item"><?php esc_html_e( 'Фільтр', 'factorial2000-catalog-sync' ); ?></a></li>
							<li><a href="#export-editor" class="f2000cs-docs-nav__item"><?php esc_html_e( 'Редактор', 'factorial2000-catalog-sync' ); ?></a></li>
							<li><a href="#export-files" class="f2000cs-docs-nav__item"><?php esc_html_e( 'Згенеровані файли', 'factorial2000-catalog-sync' ); ?></a></li>
						</ul>
					</li>
					<li>
						<a href="#marketplaces" class="f2000cs-docs-nav__item is-parent"><?php esc_html_e( 'Маркетплейси', 'factorial2000-catalog-sync' ); ?></a>
					</li>
					<li>
						<a href="#telegram" class="f2000cs-docs-nav__item is-parent"><?php esc_html_e( 'Telegram', 'factorial2000-catalog-sync' ); ?></a>
					</li>
					<li>
						<a href="#cron" class="f2000cs-docs-nav__item is-parent"><?php esc_html_e( 'Cron', 'factorial2000-catalog-sync' ); ?></a>
					</li>
					<li>
						<a href="#plans" class="f2000cs-docs-nav__item is-parent"><?php esc_html_e( 'Free і Pro', 'factorial2000-catalog-sync' ); ?></a>
					</li>
					<li>
						<a href="#faq" class="f2000cs-docs-nav__item is-parent"><?php esc_html_e( 'Часті питання', 'factorial2000-catalog-sync' ); ?></a>
					</li>
					<li>
						<a href="#developers" class="f2000cs-docs-nav__item is-parent"><?php esc_html_e( 'Для розробників', 'factorial2000-catalog-sync' ); ?></a>
					</li>
				</ul>
			</nav>

			<main class="f2000cs-docs-content">

				<section id="quickstart" class="f2000cs-docs-section">
					<h2 class="f2000cs-docs-section__title"><?php esc_html_e( 'Швидкий старт', 'factorial2000-catalog-sync' ); ?></h2>

					<div class="f2000cs-docs-card">
						<h3><?php esc_html_e( 'Три кроки до роботи', 'factorial2000-catalog-sync' ); ?></h3>
						<ol class="f2000cs-docs-steps">
							<li>
								<strong><?php esc_html_e( 'Додайте постачальника', 'factorial2000-catalog-sync' ); ?></strong>
								— <?php esc_html_e( 'Меню «Синхронізація каталогу» → «Оновлення XML»: вкажіть URL XML (YML / Prom.ua) і SKU-префікс. Збережіть налаштування.', 'factorial2000-catalog-sync' ); ?>
							</li>
							<li>
								<strong><?php esc_html_e( 'Імпортуйте товари', 'factorial2000-catalog-sync' ); ?></strong>
								— <?php esc_html_e( 'Сторінка «Імпорт XML»: завантажте файл або вкажіть URL, той самий SKU-префікс. Плагін створить товари WooCommerce (назва, SKU, ціни, опис, фото з picture, атрибути, категорії).', 'factorial2000-catalog-sync' ); ?>
							</li>
							<li>
								<strong><?php esc_html_e( 'Увімкніть автооновлення', 'factorial2000-catalog-sync' ); ?></strong>
								— <?php esc_html_e( 'На «Оновлення XML» оберіть інтервал і запустіть оновлення один раз (або дочекайтесь автоматичного запуску). Далі плагін оновлюватиме наявність і ціни з XML постачальників.', 'factorial2000-catalog-sync' ); ?>
							</li>
						</ol>
					</div>

					<div class="f2000cs-docs-card">
						<h3><?php esc_html_e( 'Що таке XML постачальника?', 'factorial2000-catalog-sync' ); ?></h3>
						<p><?php esc_html_e( 'Це файл зі списком товарів (назви, ціни, фото, наявність), який постачальник або сам Prom.ua віддає за посиланням. Зазвичай посилання є в кабінеті постачальника в розділі «Вигрузка для Prom» або «YML». Технічну будову файлу описано в розділі «Схема XML» — для щоденної роботи вона не потрібна.', 'factorial2000-catalog-sync' ); ?></p>
					</div>

					<div class="f2000cs-docs-card">
						<h3><?php esc_html_e( 'Сторінки плагіна', 'factorial2000-catalog-sync' ); ?></h3>
						<ul>
							<li><strong><?php esc_html_e( 'Оновлення XML', 'factorial2000-catalog-sync' ); ?></strong> — <?php esc_html_e( 'постачальники, розклад, Telegram, ручний запуск оновлення, стан автооновлення.', 'factorial2000-catalog-sync' ); ?></li>
							<li><strong><?php esc_html_e( 'Імпорт XML', 'factorial2000-catalog-sync' ); ?></strong> — <?php esc_html_e( 'імпорт товарів, оновлення окремих полів (Pro), налаштування обробки зображень (Pro).', 'factorial2000-catalog-sync' ); ?></li>
							<li><strong><?php esc_html_e( 'Налаштування вигрузки', 'factorial2000-catalog-sync' ); ?></strong> — <?php esc_html_e( 'фільтр і редактор XML (лише Pro).', 'factorial2000-catalog-sync' ); ?></li>
							<li><strong><?php esc_html_e( 'Маркетплейси', 'factorial2000-catalog-sync' ); ?></strong> — <?php esc_html_e( 'вигрузка товарів сайту на Prom.ua за постійним посиланням (лише Pro).', 'factorial2000-catalog-sync' ); ?></li>
							<li><strong><?php esc_html_e( 'Документація', 'factorial2000-catalog-sync' ); ?></strong> — <?php esc_html_e( 'ця сторінка.', 'factorial2000-catalog-sync' ); ?></li>
						</ul>
						<div class="f2000cs-docs-note">
							<strong><?php esc_html_e( 'Потрібен WooCommerce:', 'factorial2000-catalog-sync' ); ?></strong> <?php esc_html_e( 'меню з’являється лише коли WooCommerce активний.', 'factorial2000-catalog-sync' ); ?>
						</div>
					</div>

				<div class="f2000cs-docs-card" id="glossary">
						<h3><?php esc_html_e( 'Словничок', 'factorial2000-catalog-sync' ); ?></h3>
						<ul>
							<li><strong><?php esc_html_e( 'SKU (артикул)', 'factorial2000-catalog-sync' ); ?></strong> — <?php esc_html_e( 'унікальний код товару в магазині. Плагін складає його з префікса і артикула з XML, наприклад NEW_123.', 'factorial2000-catalog-sync' ); ?></li>
							<li><strong><?php esc_html_e( 'SKU-префікс', 'factorial2000-catalog-sync' ); ?></strong> — <?php esc_html_e( 'кілька літер на початку SKU (наприклад NEW_), щоб товари різних постачальників не змішувались.', 'factorial2000-catalog-sync' ); ?></li>
							<li><strong><?php esc_html_e( 'Offer', 'factorial2000-catalog-sync' ); ?></strong> — <?php esc_html_e( 'один товар у файлі постачальника.', 'factorial2000-catalog-sync' ); ?></li>
							<li><strong><?php esc_html_e( 'Постачальник (картка)', 'factorial2000-catalog-sync' ); ?></strong> — <?php esc_html_e( 'одне посилання на XML зі своїм префіксом і правилами цін.', 'factorial2000-catalog-sync' ); ?></li>
							<li><strong><?php esc_html_e( 'Автооновлення (WP-Cron)', 'factorial2000-catalog-sync' ); ?></strong> — <?php esc_html_e( 'вбудований у WordPress планувальник: запускає оновлення за розкладом, коли хтось відвідує сайт.', 'factorial2000-catalog-sync' ); ?></li>
							<li><strong><?php esc_html_e( 'Вигрузка', 'factorial2000-catalog-sync' ); ?></strong> — <?php esc_html_e( 'файл із товарами, який віддається назовні (для Prom.ua або для вашого використання).', 'factorial2000-catalog-sync' ); ?></li>
							<li><strong><?php esc_html_e( 'Pro', 'factorial2000-catalog-sync' ); ?></strong> — <?php esc_html_e( 'платна версія плагіна. 14 днів можна користуватись безкоштовно, без банківської картки.', 'factorial2000-catalog-sync' ); ?></li>
						</ul>
				</div>
				</section>

				<section id="xml-schema" class="f2000cs-docs-section">
					<h2 class="f2000cs-docs-section__title"><?php esc_html_e( 'Схема XML', 'factorial2000-catalog-sync' ); ?></h2>

					<div class="f2000cs-docs-card" id="xml-structure">
						<h3><?php esc_html_e( 'Структура фіду', 'factorial2000-catalog-sync' ); ?></h3>
						<p><?php esc_html_e( 'Очікуваний корінь:', 'factorial2000-catalog-sync' ); ?> <code>yml_catalog</code> → <code>shop</code>.</p>
						<ul>
							<li>
								<code>currencies</code> /
								<code>currency</code>
								— <?php esc_html_e( 'атрибути id і rate. Якщо в offer є currencyId, відмінний від валюти магазину, ціни множаться на rate.', 'factorial2000-catalog-sync' ); ?>
							</li>
							<li>
								<code>categories</code> /
								<code>category</code>
								— <?php esc_html_e( 'атрибути id, опційно parentId; текст вузла — назва. Створюються як product_cat у WooCommerce.', 'factorial2000-catalog-sync' ); ?>
							</li>
							<li>
								<code>offers</code> /
								<code>offer</code>
								— <?php esc_html_e( 'товари та варіації.', 'factorial2000-catalog-sync' ); ?>
							</li>
						</ul>
					</div>

					<div class="f2000cs-docs-card" id="xml-offer">
						<h3><?php esc_html_e( 'Поля offer', 'factorial2000-catalog-sync' ); ?></h3>
						<p><strong><?php esc_html_e( 'Атрибути тега offer', 'factorial2000-catalog-sync' ); ?></strong></p>
						<ul>
							<li><code>id</code> — <?php esc_html_e( 'артикул; під час імпорту/оновлення стає SKU з префіксом постачальника.', 'factorial2000-catalog-sync' ); ?></li>
							<li><code>available</code> — <?php esc_html_e( 'наявність. true / 1 / yes / y — «в наявності»; false / 0 / no / n — «немає в наявності». Порожній атрибут (available="", так пише OneBox) — «немає в наявності». Якщо атрибута немає взагалі — береться кількість (більше 0 — «в наявності», 0 — «немає в наявності»), а без кількості — «в наявності».', 'factorial2000-catalog-sync' ); ?></li>
							<li><code>group_id</code> — <?php esc_html_e( 'ідентифікатор групи для варіативного товару. У режимі «Прості» offers без group_id (і одиночні з group_id) стають simple; у режимі «Варіативні» без group_id або з однією offer у групі — пропускаються.', 'factorial2000-catalog-sync' ); ?></li>
						</ul>
						<p><strong><?php esc_html_e( 'Читається при імпорті (створення товарів)', 'factorial2000-catalog-sync' ); ?></strong></p>
						<ul>
							<li><code>name</code>, <code>name_ua</code> — <?php esc_html_e( 'назва; якщо name_ua не порожній — використовується він.', 'factorial2000-catalog-sync' ); ?></li>
							<li><code>description</code>, <code>description_ua</code> — <?php esc_html_e( 'опис; аналогічний пріоритет для *_ua.', 'factorial2000-catalog-sync' ); ?></li>
							<li><code>price</code> — <?php esc_html_e( 'обов’язкова ціна > 0; інакше offer пропускається.', 'factorial2000-catalog-sync' ); ?></li>
							<li><code>oldprice</code> — <?php esc_html_e( 'стара ціна (розпродаж), якщо більша за price.', 'factorial2000-catalog-sync' ); ?></li>
							<li><code>currencyId</code> — <?php esc_html_e( 'валюта offer для конвертації через currencies.', 'factorial2000-catalog-sync' ); ?></li>
							<li><code>categoryId</code> — <?php esc_html_e( 'одна або кілька категорій на offer.', 'factorial2000-catalog-sync' ); ?></li>
							<li><code>picture</code> — <?php esc_html_e( 'для simple — усі URL (обкладинка + галерея); для variable — усі фото з першого offer групи на батька, у варіації — лише перше фото offer.', 'factorial2000-catalog-sync' ); ?></li>
							<li><code>param</code> — <?php esc_html_e( 'характеристики (атрибут name обов’язковий). Опційний unit додається до значення. Кілька param з одним name об’єднуються через «; ».', 'factorial2000-catalog-sync' ); ?></li>
							<li><code>vendor</code> — <?php esc_html_e( 'виробник; якщо немає атрибута «Виробник», додається як param.', 'factorial2000-catalog-sync' ); ?></li>
							<li><code>weight</code> — <?php esc_html_e( 'вага → _weight (лише якщо > 0).', 'factorial2000-catalog-sync' ); ?></li>
							<li><code>barcode</code> — <?php esc_html_e( 'штрихкод → _barcode.', 'factorial2000-catalog-sync' ); ?></li>
							<li><code>dimensions</code> — <?php esc_html_e( 'габарити як рядок → _dimensions (не розбивається на length/width/height WooCommerce).', 'factorial2000-catalog-sync' ); ?></li>
						</ul>
						<p><strong><?php esc_html_e( 'Читається при оновленні стоку (Оновлення XML)', 'factorial2000-catalog-sync' ); ?></strong></p>
						<ul>
							<li><code>id</code>, <code>available</code>, <code>group_id</code></li>
							<li><code>price</code>, <code>oldprice</code>, <code>currencyId</code> — <?php esc_html_e( 'якщо не увімкнено «не змінювати ціни».', 'factorial2000-catalog-sync' ); ?></li>
							<li><code>vendorCode</code> — <?php esc_html_e( 'у службове поле товару (f2000cs-updater-vendor), лише якщо воно ще порожнє.', 'factorial2000-catalog-sync' ); ?></li>
							<li><code>quantity</code> / <code>stock_quantity</code> / <code>qty_stock</code> — <?php esc_html_e( 'кількість на складі (Pro, опція в картці постачальника). Пріоритет: quantity, потім stock_quantity, потім qty_stock; значення на кшталт 1.000 читається як 1.', 'factorial2000-catalog-sync' ); ?></li>
						</ul>
						<p><strong><?php esc_html_e( 'Додатково в «Оновленні окремих полів» (Pro)', 'factorial2000-catalog-sync' ); ?></strong></p>
						<ul>
							<li><code>short_description</code>, <code>short_description_ua</code></li>
							<li><code>keywords</code> — <?php esc_html_e( 'теги WooCommerce (роздільники «,» або «;»).', 'factorial2000-catalog-sync' ); ?></li>
							<li><code>vendorCode</code> — <?php esc_html_e( 'примусове оновлення метаполя (на відміну від оновлення наявності).', 'factorial2000-catalog-sync' ); ?></li>
							<li><?php esc_html_e( 'а також name/description, ціни, available, quantity, picture, param, categoryId — за вибраними чекбоксами.', 'factorial2000-catalog-sync' ); ?></li>
						</ul>
						<div class="f2000cs-docs-note">
							<strong><?php esc_html_e( 'Важливо:', 'factorial2000-catalog-sync' ); ?></strong>
							<?php esc_html_e( 'vendorCode при першому імпорті (створенні товару) не записується — лише після оновлення наявності або оновлення окремих полів. Кількість при імпорті виставляється лише з увімкненою Pro-опцією «Імпортувати кількість товару (Stock quantity) з XML»; без неї облік запасів для товару не вмикається.', 'factorial2000-catalog-sync' ); ?>
						</div>
					</div>

					<div class="f2000cs-docs-card" id="xml-example">
						<h3><?php esc_html_e( 'Приклад повної схеми', 'factorial2000-catalog-sync' ); ?></h3>
						<pre class="f2000cs-docs-code">&lt;yml_catalog date="2026-08-01 12:00"&gt;
	&lt;shop&gt;
		&lt;currencies&gt;
			&lt;currency id="UAH" rate="1"/&gt;
			&lt;currency id="USD" rate="41.5"/&gt;
		&lt;/currencies&gt;
		&lt;categories&gt;
			&lt;category id="1"&gt;Взуття&lt;/category&gt;
			&lt;category id="2" parentId="1"&gt;Кросівки&lt;/category&gt;
		&lt;/categories&gt;
		&lt;offers&gt;
			&lt;!-- Простий товар --&gt;
			&lt;offer id="1001" available="true"&gt;
				&lt;name&gt;Кросівки Road&lt;/name&gt;
				&lt;name_ua&gt;Кросівки Road&lt;/name_ua&gt;
				&lt;description&gt;&lt;![CDATA[&lt;p&gt;Опис HTML&lt;/p&gt;]]&gt;&lt;/description&gt;
				&lt;price&gt;1999&lt;/price&gt;
				&lt;oldprice&gt;2499&lt;/oldprice&gt;
				&lt;currencyId&gt;UAH&lt;/currencyId&gt;
				&lt;categoryId&gt;2&lt;/categoryId&gt;
				&lt;picture&gt;https://example.com/img1.jpg&lt;/picture&gt;
				&lt;picture&gt;https://example.com/img2.jpg&lt;/picture&gt;
				&lt;vendor&gt;Nike&lt;/vendor&gt;
				&lt;vendorCode&gt;SUP-55&lt;/vendorCode&gt;
				&lt;barcode&gt;4820000000001&lt;/barcode&gt;
				&lt;weight&gt;0.8&lt;/weight&gt;
				&lt;dimensions&gt;30/20/12&lt;/dimensions&gt;
				&lt;quantity&gt;5&lt;/quantity&gt;
				&lt;param name="Колір"&gt;Чорний&lt;/param&gt;
				&lt;param name="Матеріал"&gt;Шкіра&lt;/param&gt;
			&lt;/offer&gt;
			&lt;!-- Варіації однієї групи --&gt;
			&lt;offer id="2001" available="true" group_id="2000"&gt;
				&lt;name&gt;Кросівки 42&lt;/name&gt;
				&lt;price&gt;2199&lt;/price&gt;
				&lt;categoryId&gt;2&lt;/categoryId&gt;
				&lt;picture&gt;https://example.com/var.jpg&lt;/picture&gt;
				&lt;param name="Розмір" unit="EU"&gt;42&lt;/param&gt;
				&lt;param name="Колір"&gt;Білий&lt;/param&gt;
			&lt;/offer&gt;
			&lt;offer id="2002" available="false" group_id="2000"&gt;
				&lt;name&gt;Кросівки 43&lt;/name&gt;
				&lt;price&gt;2199&lt;/price&gt;
				&lt;categoryId&gt;2&lt;/categoryId&gt;
				&lt;param name="Розмір" unit="EU"&gt;43&lt;/param&gt;
				&lt;param name="Колір"&gt;Білий&lt;/param&gt;
			&lt;/offer&gt;
		&lt;/offers&gt;
	&lt;/shop&gt;
&lt;/yml_catalog&gt;</pre>
					</div>
				</section>

				<section id="suppliers" class="f2000cs-docs-section">
					<h2 class="f2000cs-docs-section__title"><?php esc_html_e( 'Постачальники', 'factorial2000-catalog-sync' ); ?></h2>

					<div class="f2000cs-docs-card" id="suppliers-url">
						<h3><?php esc_html_e( 'URL XML-файлу', 'factorial2000-catalog-sync' ); ?></h3>
						<p><?php esc_html_e( 'Повне посилання на XML постачальника, наприклад:', 'factorial2000-catalog-sync' ); ?> <code>https://example.com/export/prom.xml</code></p>
						<p><?php esc_html_e( 'Під час кожного циклу оновлення плагін завантажує цей URL. Файл має бути доступний публічно (без логіна). Якщо XML закритий авторизацією або IP-фільтром — зверніться до постачальника.', 'factorial2000-catalog-sync' ); ?></p>
					</div>

					<div class="f2000cs-docs-card" id="suppliers-prefix">
						<h3><?php esc_html_e( 'SKU-префікс', 'factorial2000-catalog-sync' ); ?></h3>
						<p><?php esc_html_e( 'Унікальний префікс (наприклад NEW_), який додається до артикула з XML (offer id). Потрібен, щоб:', 'factorial2000-catalog-sync' ); ?></p>
						<ul>
							<li><?php esc_html_e( 'не змішувати однакові артикули різних постачальників;', 'factorial2000-catalog-sync' ); ?></li>
							<li><?php esc_html_e( 'знаходити товар при оновленні стоку саме за SKU з префіксом;', 'factorial2000-catalog-sync' ); ?></li>
							<li><?php esc_html_e( 'при імпорті/оновленні полів використовувати той самий префікс, що й у картці постачальника.', 'factorial2000-catalog-sync' ); ?></li>
						</ul>
					</div>

					<div class="f2000cs-docs-card" id="suppliers-flags">
						<h3><?php esc_html_e( 'Ціна та кількість', 'factorial2000-catalog-sync' ); ?></h3>
						<ul>
							<li><strong><?php esc_html_e( 'Не змінювати ціни…', 'factorial2000-catalog-sync' ); ?></strong> — <?php esc_html_e( 'під час оновлення наявності оновлюється лише наявність (і кількість, якщо увімкнено), ціни з XML не перезаписуються.', 'factorial2000-catalog-sync' ); ?></li>
							<li><strong><?php esc_html_e( 'Оновлювати кількість товарів', 'factorial2000-catalog-sync' ); ?></strong> <span class="f2000cs-pro-badge">Pro</span> — <?php esc_html_e( 'якщо в XML є quantity / stock_quantity / qty_stock — записує залишок у WooCommerce. Без опції оновлюється лише «є / немає в наявності».', 'factorial2000-catalog-sync' ); ?></li>
						</ul>
					</div>

					<div class="f2000cs-docs-card" id="suppliers-price">
						<h3><?php esc_html_e( 'Коригування ціни', 'factorial2000-catalog-sync' ); ?></h3>
						<p><?php esc_html_e( 'Застосовується при оновленні цін під час оновлення наявності (якщо не увімкнено «не змінювати ціни»). Три типи:', 'factorial2000-catalog-sync' ); ?></p>
						<ul>
							<li><strong><?php esc_html_e( 'Націнка (%)', 'factorial2000-catalog-sync' ); ?></strong> — <?php esc_html_e( 'відсоток від ціни постачальника (додати або відняти).', 'factorial2000-catalog-sync' ); ?></li>
							<li><strong><?php esc_html_e( 'Маржа (%)', 'factorial2000-catalog-sync' ); ?></strong> — <?php esc_html_e( 'бажаний прибуток у % від кінцевої ціни (ціна = собівартість / (1 − маржа)).', 'factorial2000-catalog-sync' ); ?></li>
							<li><strong><?php esc_html_e( 'Фіксована сума', 'factorial2000-catalog-sync' ); ?></strong> — <?php esc_html_e( 'додати або відняти фіксовану суму у валюті магазину.', 'factorial2000-catalog-sync' ); ?></li>
						</ul>
					</div>

					<div class="f2000cs-docs-card" id="suppliers-slots">
						<h3><?php esc_html_e( 'Слоти постачальників', 'factorial2000-catalog-sync' ); ?></h3>
						<p><?php esc_html_e( 'Кожен постачальник — окрема картка зі своїм URL, SKU-префіксом і правилами цін.', 'factorial2000-catalog-sync' ); ?></p>
						<ul>
							<li><strong><?php esc_html_e( 'Free:', 'factorial2000-catalog-sync' ); ?></strong> <?php esc_html_e( '1 новий слот. Уже збережені додаткові слоти (якщо були) можна редагувати, але додати нові — лише в Pro.', 'factorial2000-catalog-sync' ); ?></li>
							<li><strong><?php esc_html_e( 'Pro / тріал:', 'factorial2000-catalog-sync' ); ?></strong> <?php esc_html_e( 'можна додавати багато постачальників (технічний ліміт сканування слотів у плагіні — до 200).', 'factorial2000-catalog-sync' ); ?></li>
						</ul>
					</div>
				</section>

				<section id="update" class="f2000cs-docs-section">
					<h2 class="f2000cs-docs-section__title"><?php esc_html_e( 'Оновлення XML (синхронізація стоку)', 'factorial2000-catalog-sync' ); ?></h2>

					<div class="f2000cs-docs-card" id="update-modes">
						<h3><?php esc_html_e( 'Режими запуску', 'factorial2000-catalog-sync' ); ?></h3>
						<ul>
							<li>
								<strong><?php esc_html_e( 'Фоновий (рекомендовано)', 'factorial2000-catalog-sync' ); ?></strong>
								— <?php esc_html_e( 'задача ставиться в WP-Cron і стартує приблизно через 30 секунд. Сторінка адмінки не блокується.', 'factorial2000-catalog-sync' ); ?>
							</li>
							<li>
								<strong><?php esc_html_e( 'Одразу', 'factorial2000-catalog-sync' ); ?></strong>
								— <?php esc_html_e( 'оновлення виконується в момент натискання; сторінка чекає завершення. Зручно для невеликих каталогів.', 'factorial2000-catalog-sync' ); ?>
							</li>
						</ul>
					</div>

					<div class="f2000cs-docs-card" id="update-interval">
						<h3><?php esc_html_e( 'Інтервал автооновлення', 'factorial2000-catalog-sync' ); ?></h3>
						<ul>
							<li><?php esc_html_e( 'Pro: що 5 / 10 / 15 / 30 хвилин, щогодини, 8 / 6 / 4 рази на день, двічі на день, щодня.', 'factorial2000-catalog-sync' ); ?></li>
							<li><?php esc_html_e( 'Free: лише «Щодня»; частіші інтервали недоступні.', 'factorial2000-catalog-sync' ); ?></li>
						</ul>
						<div class="f2000cs-docs-note">
							<strong><?php esc_html_e( 'Free:', 'factorial2000-catalog-sync' ); ?></strong> <?php esc_html_e( 'також не більше 3 ручних запусків оновлення на добу (автооновлення за розкладом у ліміт не входить).', 'factorial2000-catalog-sync' ); ?>
						</div>
					</div>

					<div class="f2000cs-docs-card" id="update-prices">
						<h3><?php esc_html_e( 'Що оновлює оновлення наявності', 'factorial2000-catalog-sync' ); ?></h3>
						<ul>
							<li><?php esc_html_e( 'Наявність («в наявності» / «немає в наявності») за атрибутом available у offer.', 'factorial2000-catalog-sync' ); ?></li>
							<li><?php esc_html_e( 'Ціни (якщо не увімкнено «не змінювати ціни»), з урахуванням коригування Pro.', 'factorial2000-catalog-sync' ); ?></li>
							<li><?php esc_html_e( 'Кількість на складі — Pro-опція; теги quantity, stock_quantity або qty_stock.', 'factorial2000-catalog-sync' ); ?></li>
							<li><?php esc_html_e( 'vendorCode — у метаполе менеджера, якщо воно ще порожнє.', 'factorial2000-catalog-sync' ); ?></li>
						</ul>
						<p><?php esc_html_e( 'Описи, фото, атрибути та категорії cron не змінює — для цього є «Оновлення окремих полів» на сторінці імпорту (Pro).', 'factorial2000-catalog-sync' ); ?></p>
					</div>

					<div class="f2000cs-docs-card" id="update-missing">
						<h3><?php esc_html_e( 'Товари, що зникли з XML', 'factorial2000-catalog-sync' ); ?></h3>
						<p><?php esc_html_e( 'Якщо для постачальника задано SKU-префікс, після обходу фіду плагін знаходить товари з цим префіксом, яких немає в поточному XML:', 'factorial2000-catalog-sync' ); ?></p>
						<ul>
							<li><?php esc_html_e( 'прості товари (simple) → статус «Чернетка»;', 'factorial2000-catalog-sync' ); ?></li>
							<li><?php esc_html_e( 'варіації → «Немає в наявності» (батьківський variable не переводиться в чернетку);', 'factorial2000-catalog-sync' ); ?></li>
							<li><?php esc_html_e( 'без SKU-префікса ця перевірка не виконується.', 'factorial2000-catalog-sync' ); ?></li>
							<li><?php esc_html_e( 'Товар, який плагін сам перевів у «Чернетку», автоматично публікується знову, щойно з’являється у вигрузці (товари, які ви перевели в чернетку вручну, не чіпаються).', 'factorial2000-catalog-sync' ); ?></li>
						</ul>
						<p><?php esc_html_e( 'Важливо: XML має містити повний каталог. Неповний фід може масово сховати прості товари.', 'factorial2000-catalog-sync' ); ?></p>
						<div class="f2000cs-docs-note f2000cs-docs-note--warn">
							<strong><?php esc_html_e( 'Увага:', 'factorial2000-catalog-sync' ); ?></strong> <?php esc_html_e( 'Якщо XML тимчасово неповний або недоступний — частина товарів може піти в чернетку. Переконайтеся, що фід завжди містить повний асортимент.', 'factorial2000-catalog-sync' ); ?>
						</div>
					</div>

					<div class="f2000cs-docs-card" id="update-variable-low">
						<h3><?php esc_html_e( 'Мала наявність варіацій', 'factorial2000-catalog-sync' ); ?> <span class="f2000cs-pro-badge">Pro</span></h3>
						<p><?php esc_html_e( 'Після оновлення наявності можна автоматично позначати варіативний товар як «немає в наявності», якщо кількість варіацій «в наявності» не більша за поріг (за замовчуванням 2). Корисно, щоб у рекламі не крутились товари з майже порожньою сіткою розмірів.', 'factorial2000-catalog-sync' ); ?></p>
					</div>

					<div class="f2000cs-docs-card" id="update-vendor">
						<h3><?php esc_html_e( 'Vendor Code на товарі', 'factorial2000-catalog-sync' ); ?></h3>
						<p><?php esc_html_e( 'Опція «Показувати vendorCode адміну на сторінці товару» (Оновлення XML → Розклад і правила). Якщо увімкнено — адміністратор (manage_options) бачить у підвалі сторінки товару код з метаполя f2000cs-updater-vendor і може скопіювати кліком. Покупці блок не бачать. За замовчуванням увімкнено.', 'factorial2000-catalog-sync' ); ?></p>
					</div>

				<div class="f2000cs-docs-card" id="update-ssl">
						<h3><?php esc_html_e( 'Слабкі SSL-сертифікати', 'factorial2000-catalog-sync' ); ?></h3>
						<p><?php esc_html_e( 'Коли плагін завантажує XML і фото, він перевіряє сертифікат безпеки (https) сайту постачальника. Якщо в постачальника сертифікат прострочений або «самопідписаний», файл не завантажиться. Тоді на сторінці «Оновлення XML» (блок «Розклад і правила») увімкніть «Слабкі SSL-сертифікати».', 'factorial2000-catalog-sync' ); ?></p>
						<div class="f2000cs-docs-note"><strong><?php esc_html_e( 'Увага:', 'factorial2000-catalog-sync' ); ?></strong> <?php esc_html_e( 'перевірка вимикається для всіх завантажень XML і фото. Вмикайте лише якщо довіряєте постачальнику. Розробники можуть керувати цим фільтром f2000cs_ssl_verify.', 'factorial2000-catalog-sync' ); ?></div>
				</div>

				<div class="f2000cs-docs-card" id="update-status">
						<h3><?php esc_html_e( 'Панель статусу', 'factorial2000-catalog-sync' ); ?></h3>
						<ul>
							<li><strong><?php esc_html_e( 'Ліміт Free сьогодні', 'factorial2000-catalog-sync' ); ?></strong> — <?php esc_html_e( 'скільки з 3 ручних запусків на добу вже використано (лише у Free).', 'factorial2000-catalog-sync' ); ?></li>
							<li><strong><?php esc_html_e( 'Автооновлення', 'factorial2000-catalog-sync' ); ?></strong> — <?php esc_html_e( 'активне чи зупинене.', 'factorial2000-catalog-sync' ); ?></li>
							<li><strong><?php esc_html_e( 'Наступний запуск', 'factorial2000-catalog-sync' ); ?></strong> — <?php esc_html_e( 'коли оновлення стартує за розкладом.', 'factorial2000-catalog-sync' ); ?></li>
							<li><strong><?php esc_html_e( 'Інтервал', 'factorial2000-catalog-sync' ); ?></strong> — <?php esc_html_e( 'як часто воно запускається.', 'factorial2000-catalog-sync' ); ?></li>
							<li><strong><?php esc_html_e( 'Фонове оновлення', 'factorial2000-catalog-sync' ); ?></strong> — <?php esc_html_e( '«В черзі» — задачу вже поставлено; «Заплановано на» — час її старту.', 'factorial2000-catalog-sync' ); ?></li>
						</ul>
				</div>
				</section>

				<section id="import" class="f2000cs-docs-section">
					<h2 class="f2000cs-docs-section__title"><?php esc_html_e( 'Імпорт товарів з XML', 'factorial2000-catalog-sync' ); ?></h2>

					<div class="f2000cs-docs-card" id="import-sources">
						<h3><?php esc_html_e( 'Джерела імпорту', 'factorial2000-catalog-sync' ); ?></h3>
						<ul>
							<li><strong><?php esc_html_e( 'URL', 'factorial2000-catalog-sync' ); ?></strong> — <?php esc_html_e( 'пряме посилання на XML/YML; підтримує продовження після обриву.', 'factorial2000-catalog-sync' ); ?></li>
							<li><strong><?php esc_html_e( 'Файл', 'factorial2000-catalog-sync' ); ?></strong> — <?php esc_html_e( 'завантаження .xml з комп’ютера; зручно для тесту або разового імпорту.', 'factorial2000-catalog-sync' ); ?></li>
						</ul>
						<p><?php esc_html_e( 'Обов’язково вкажіть SKU-префікс (той самий, що в картці постачальника). Опційно — додавати нові товари в категорію «Новинки» (за потреби категорії з вигрузки вкладаються під «Новинки» як підкатегорії). Pro: опція «Імпортувати кількість товару (Stock quantity) з XML» записує залишок і вмикає керування запасами. Опція «Імпортувати тільки товари в наявності» пропускає товари, яких немає в наявності; варіативний товар отримує лише варіації в наявності.', 'factorial2000-catalog-sync' ); ?></p>
						<div class="f2000cs-docs-note">
							<strong><?php esc_html_e( 'Формат:', 'factorial2000-catalog-sync' ); ?></strong>
							<?php esc_html_e( 'YML / Prom.ua. Повний перелік полів і приклад — у розділі «Схема XML».', 'factorial2000-catalog-sync' ); ?>
						</div>
					</div>

					<div class="f2000cs-docs-card" id="import-simple">
						<h3><?php esc_html_e( 'Прості товари', 'factorial2000-catalog-sync' ); ?></h3>
						<p><?php esc_html_e( 'Режим «Прості продукти»: імпортуються offers без group_id, а також групи з рівно одним offer. Групи з 2+ offers у цьому режимі пропускаються (їх імпортуйте в режимі варіативних). Для кожного підходящого offer створюється Simple product:', 'factorial2000-catalog-sync' ); ?></p>
						<ul>
							<li><?php esc_html_e( 'назва (name / name_ua), SKU з префіксом, опис;', 'factorial2000-catalog-sync' ); ?></li>
							<li><?php esc_html_e( 'ціна, oldprice, конвертація за currencyId;', 'factorial2000-catalog-sync' ); ?></li>
							<li><?php esc_html_e( 'категорії з одного або кількох categoryId;', 'factorial2000-catalog-sync' ); ?></li>
							<li><?php esc_html_e( 'зображення з усіх picture;', 'factorial2000-catalog-sync' ); ?></li>
							<li><?php esc_html_e( 'атрибути з param (і unit); vendor → «Виробник»;', 'factorial2000-catalog-sync' ); ?></li>
							<li><?php esc_html_e( 'weight, barcode, dimensions → відповідні метаполя;', 'factorial2000-catalog-sync' ); ?></li>
							<li><?php esc_html_e( 'наявність за available.', 'factorial2000-catalog-sync' ); ?></li>
						</ul>
						<p><?php esc_html_e( 'Товар із уже існуючим SKU (з префіксом) повторно не створюється. vendorCode при створенні не пишеться, а кількість — лише з опцією «Імпортувати кількість товару» (Pro) — див. «Схема XML». Повторний імпорт існуючі товари не оновлює: для актуальних залишків використовуйте «Оновлення XML» за URL. Завантажений файл в автооновлення не потрапляє.', 'factorial2000-catalog-sync' ); ?></p>
					</div>

					<div class="f2000cs-docs-card" id="import-variable">
						<h3><?php esc_html_e( 'Варіативні товари', 'factorial2000-catalog-sync' ); ?></h3>
						<p><?php esc_html_e( 'Режим «Варіативні продукти»: натисніть «Проаналізувати XML». Плагін знайде групи за group_id з 2+ offers, покаже атрибути (з unit, як при імпорті) і позначить ті, що варіюються. Оберіть атрибути й запустіть імпорт.', 'factorial2000-catalog-sync' ); ?></p>
						<ul>
							<li><?php esc_html_e( 'SKU батька = префікс + group_id; SKU варіації = префікс + offer id.', 'factorial2000-catalog-sync' ); ?></li>
							<li><?php esc_html_e( 'На кожен товар групи створюється одна варіація (наприклад, один розмір — одна варіація). Усі можливі комбінації атрибутів плагін не створює.', 'factorial2000-catalog-sync' ); ?></li>
							<li><?php esc_html_e( 'Назва/опис/категорії/фото батька беруться з першого offer групи (назва — спільна база з titles).', 'factorial2000-catalog-sync' ); ?></li>
							<li><?php esc_html_e( 'Offers без group_id і групи з 1 offer у цьому режимі пропускаються.', 'factorial2000-catalog-sync' ); ?></li>
							<li><?php esc_html_e( 'Якщо жоден атрибут не підходить, плагін обере сам: спочатку розмір, потім колір, потім перший, значення якого відрізняються.', 'factorial2000-catalog-sync' ); ?></li>
							<li><?php esc_html_e( 'Варіація з порожнім SKU/назвою або price ≤ 0 пропускається; якщо не створилось жодної — батько видаляється.', 'factorial2000-catalog-sync' ); ?></li>
						</ul>
						<div class="f2000cs-docs-note">
							<strong><?php esc_html_e( 'Підказка:', 'factorial2000-catalog-sync' ); ?></strong> <?php esc_html_e( 'Обирайте атрибути з різними значеннями між offers (Розмір, Колір). Для каталогу з simple + variable спочатку імпортуйте прості, потім варіативні (або навпаки) з тим самим SKU-префіксом.', 'factorial2000-catalog-sync' ); ?>
						</div>
					</div>

					<div class="f2000cs-docs-card" id="import-resume">
						<h3><?php esc_html_e( 'Продовження перерваного імпорту', 'factorial2000-catalog-sync' ); ?></h3>
						<ul>
							<li><?php esc_html_e( 'При наступному відкритті «Імпорт XML» може з’явитись пропозиція продовжити або скасувати.', 'factorial2000-catalog-sync' ); ?></li>
							<li><?php esc_html_e( 'Сесія імпорту зазвичай зберігається близько години.', 'factorial2000-catalog-sync' ); ?></li>
							<li><?php esc_html_e( 'Надійне продовження — для імпорту за URL; після завантаження файлу з ПК при обриві часто потрібно почати знову.', 'factorial2000-catalog-sync' ); ?></li>
						</ul>
					</div>

					<div class="f2000cs-docs-card" id="import-images">
						<h3><?php esc_html_e( 'Зображення при імпорті', 'factorial2000-catalog-sync' ); ?> <span class="f2000cs-pro-badge">Pro</span></h3>
						<p><?php esc_html_e( 'Блок на сторінці «Імпорт XML» (зберігається окремою кнопкою). Діє лише на файли з тегів picture під час імпорту або оновлення поля «Фото». HTML у описах не змінюється.', 'factorial2000-catalog-sync' ); ?></p>
						<ul>
							<li><strong><?php esc_html_e( 'Формат зображень', 'factorial2000-catalog-sync' ); ?></strong> — <?php esc_html_e( 'фото з picture у PNG, JPG, WebP або AVIF конвертуються у вибраний формат через Imagick/GD WordPress. GIF і BMP не змінюються. JPG сумісніший; WebP/AVIF — якщо хостинг підтримує. При помилці лишається оригінал.', 'factorial2000-catalog-sync' ); ?></li>
							<li><strong><?php esc_html_e( 'Оптимізація', 'factorial2000-catalog-sync' ); ?></strong> — <?php esc_html_e( 'перезбереження з обраною якістю.', 'factorial2000-catalog-sync' ); ?></li>
							<li><strong><?php esc_html_e( 'Якість', 'factorial2000-catalog-sync' ); ?></strong> — <?php esc_html_e( '40–100 для оптимізації/конвертації.', 'factorial2000-catalog-sync' ); ?></li>
							<li><strong><?php esc_html_e( 'Макс. сторона', 'factorial2000-catalog-sync' ); ?></strong> — <?php esc_html_e( 'зменшення зі збереженням пропорцій; 0 = без ліміту.', 'factorial2000-catalog-sync' ); ?></li>
							<li><strong><?php esc_html_e( 'Цей хостинг', 'factorial2000-catalog-sync' ); ?></strong> — <?php esc_html_e( 'перевірка Imagick, GD, JPG, WebP, AVIF і resize на сервері.', 'factorial2000-catalog-sync' ); ?></li>
						</ul>
					</div>
				</section>

				<section id="fields-update" class="f2000cs-docs-section">
					<h2 class="f2000cs-docs-section__title"><?php esc_html_e( 'Оновлення окремих полів', 'factorial2000-catalog-sync' ); ?> <span class="f2000cs-pro-badge">Pro</span></h2>

					<div class="f2000cs-docs-card" id="fields-available">
						<h3><?php esc_html_e( 'Які поля можна оновити', 'factorial2000-catalog-sync' ); ?></h3>
						<p><?php esc_html_e( 'На сторінці «Імпорт XML», вкладка оновлення полів: обираєте поля, URL/файл XML і SKU-префікс. Плагін знаходить уже існуючі товари за SKU і оновлює лише вибране. Нові товари не створюються.', 'factorial2000-catalog-sync' ); ?></p>
						<ul>
							<li><?php esc_html_e( 'Назва', 'factorial2000-catalog-sync' ); ?></li>
							<li><?php esc_html_e( 'Опис і короткий опис', 'factorial2000-catalog-sync' ); ?></li>
							<li><?php esc_html_e( 'Теги (мітки)', 'factorial2000-catalog-sync' ); ?></li>
							<li><?php esc_html_e( 'Ціна та стара ціна (oldprice)', 'factorial2000-catalog-sync' ); ?></li>
							<li><?php esc_html_e( 'Статус товару', 'factorial2000-catalog-sync' ); ?></li>
							<li><?php esc_html_e( 'Кількість на складі', 'factorial2000-catalog-sync' ); ?></li>
							<li><?php esc_html_e( 'Зображення (picture)', 'factorial2000-catalog-sync' ); ?></li>
							<li><?php esc_html_e( 'Атрибути (param; vendor → атрибут «Виробник»)', 'factorial2000-catalog-sync' ); ?></li>
							<li><?php esc_html_e( 'Категорії', 'factorial2000-catalog-sync' ); ?></li>
							<li><?php esc_html_e( 'vendorCode → метаполе для копіювання адміном', 'factorial2000-catalog-sync' ); ?></li>
						</ul>
					</div>

					<div class="f2000cs-docs-card" id="fields-images">
						<h3><?php esc_html_e( 'Оновлення зображень', 'factorial2000-catalog-sync' ); ?></h3>
						<p><?php esc_html_e( 'Картинки з picture завантажуються в медіатеку. Якщо увімкнено Pro-налаштування зображень на сторінці імпорту — застосовується конвертація/оптимізація/ліміт розміру так само, як при імпорті.', 'factorial2000-catalog-sync' ); ?></p>
					</div>
				</section>

				<section id="export" class="f2000cs-docs-section">
					<h2 class="f2000cs-docs-section__title"><?php esc_html_e( 'Вигрузка XML', 'factorial2000-catalog-sync' ); ?> <span class="f2000cs-pro-badge">Pro</span></h2>

					<div class="f2000cs-docs-card">
						<p><?php esc_html_e( 'Сторінка «Налаштування вигрузки» доступна лише в Pro. Працює з XML-фідом (файл або URL), а не з експортом каталогу WooCommerce «з нуля».', 'factorial2000-catalog-sync' ); ?></p>
					</div>

					<div class="f2000cs-docs-card" id="export-filter">
						<h3><?php esc_html_e( 'Фільтр XML вигрузки', 'factorial2000-catalog-sync' ); ?></h3>
						<p><?php esc_html_e( 'Завантажуєте XML постачальника і отримуєте новий файл без товарів, які вже є на сайті (порівняння за SKU з урахуванням префікса). Опційно — мінімальна ціна. Зручно перед імпортом лише новинок.', 'factorial2000-catalog-sync' ); ?></p>
					</div>

					<div class="f2000cs-docs-card" id="export-editor">
						<h3><?php esc_html_e( 'Редактор вигрузок XML', 'factorial2000-catalog-sync' ); ?></h3>
						<p><?php esc_html_e( 'Завантажуєте великий фід (URL або файл), обираєте категорії та товари, умови:', 'factorial2000-catalog-sync' ); ?></p>
						<ul>
							<li><?php esc_html_e( 'лише в наявності;', 'factorial2000-catalog-sync' ); ?></li>
							<li><?php esc_html_e( 'мін./макс. ціна;', 'factorial2000-catalog-sync' ); ?></li>
							<li><?php esc_html_e( 'залишати чи прибрати oldprice.', 'factorial2000-catalog-sync' ); ?></li>
						</ul>
						<p><?php esc_html_e( '«Сформувати XML» зберігає файл у uploads. Для фіду понад ~50 МБ з’явиться попередження — краще звузити вибір категорій.', 'factorial2000-catalog-sync' ); ?></p>
					</div>

					<div class="f2000cs-docs-card" id="export-files">
						<h3><?php esc_html_e( 'Останні згенеровані XML', 'factorial2000-catalog-sync' ); ?></h3>
						<p><?php esc_html_e( 'На сторінці вигрузки показується список нещодавно створених файлів (фільтр і редактор) із посиланнями для завантаження. Окремих «профілів вигрузки» чи шорткодів для URL немає — беріть пряме посилання з цього списку або з повідомлення після генерації.', 'factorial2000-catalog-sync' ); ?></p>
					</div>
				</section>

				<section id="marketplaces" class="f2000cs-docs-section">
					<h2 class="f2000cs-docs-section__title"><?php esc_html_e( 'Маркетплейси', 'factorial2000-catalog-sync' ); ?> <span class="f2000cs-pro-badge">Pro</span></h2>

				<div class="f2000cs-docs-card" id="marketplaces-prom">
						<h3><?php esc_html_e( 'Вигрузка товарів WooCommerce на Prom.ua', 'factorial2000-catalog-sync' ); ?></h3>
						<p><?php esc_html_e( 'Сторінка «Маркетплейси» збирає з ваших товарів WooCommerce файл для Prom.ua і дає на нього постійне посилання. Prom сам заходить за цим посиланням і підтягує ваші товари, ціни та наявність. Тобто це зворотний напрям до імпорту: товари йдуть з вашого сайту на Prom, а не від постачальника на сайт.', 'factorial2000-catalog-sync' ); ?></p>
						<ol class="f2000cs-docs-steps">
							<li><?php esc_html_e( 'Відкрийте «Синхронізація каталогу» → «Маркетплейси». Для початку підійдуть налаштування за замовчуванням.', 'factorial2000-catalog-sync' ); ?></li>
							<li><?php esc_html_e( 'Натисніть «Згенерувати зараз» і дочекайтесь напису «Готово!». Великий каталог збирається кілька хвилин; якщо збирання перервалось, натисніть «Продовжити генерацію».', 'factorial2000-catalog-sync' ); ?></li>
							<li><?php esc_html_e( 'Скопіюйте «Посилання на вигрузку».', 'factorial2000-catalog-sync' ); ?></li>
							<li><?php esc_html_e( 'У кабінеті Prom.ua додайте це посилання як джерело імпорту товарів (імпорт за посиланням) і виберіть, як часто Prom його перечитує.', 'factorial2000-catalog-sync' ); ?></li>
							<li><?php esc_html_e( 'Щоб Prom завжди бачив свіжі ціни й наявність, увімкніть «Після оновлення наявності» та/або виберіть розклад.', 'factorial2000-catalog-sync' ); ?></li>
						</ol>
						<div class="f2000cs-docs-note"><strong><?php esc_html_e( 'Безпека:', 'factorial2000-catalog-sync' ); ?></strong> <?php esc_html_e( 'посилання містить секретний код. Кожен, хто його знає, може прочитати дані ваших товарів — так само, як Prom. Не публікуйте його відкрито.', 'factorial2000-catalog-sync' ); ?></div>
				</div>

				<div class="f2000cs-docs-card" id="marketplaces-settings">
						<h3><?php esc_html_e( 'Налаштування вигрузки', 'factorial2000-catalog-sync' ); ?></h3>
						<ul>
							<li><strong><?php esc_html_e( 'Магазин', 'factorial2000-catalog-sync' ); ?></strong> — <?php esc_html_e( 'назва магазину й компанії, адреса сайту та валюта цін. За замовчуванням беруться з налаштувань WordPress і WooCommerce.', 'factorial2000-catalog-sync' ); ?></li>
							<li><strong><?php esc_html_e( 'Фото на товар', 'factorial2000-catalog-sync' ); ?></strong> — <?php esc_html_e( 'скільки фото передавати в Prom (Prom приймає до 10).', 'factorial2000-catalog-sync' ); ?></li>
							<li><strong><?php esc_html_e( 'Неопубліковані товари', 'factorial2000-catalog-sync' ); ?></strong> — <?php esc_html_e( 'за замовчуванням у файл потрапляють лише опубліковані товари; цією опцією можна додати й чернетки.', 'factorial2000-catalog-sync' ); ?></li>
							<li><strong><?php esc_html_e( 'Якщо в товара немає опису', 'factorial2000-catalog-sync' ); ?></strong> — <?php esc_html_e( 'Prom вимагає опис, тому після назви додається коротка фраза. Можна залишити лише назву або написати свій текст.', 'factorial2000-catalog-sync' ); ?></li>
							<li><strong><?php esc_html_e( 'Виробник (бренд)', 'factorial2000-catalog-sync' ); ?></strong> — <?php esc_html_e( 'береться бренд товару. Якщо його немає — підставляється «Виробник, якщо бренд не вказано» (наприклад, «Без бренду»). Прапорець «Завжди цей виробник» замінює бренди всіх товарів одним значенням, зазвичай він не потрібен. У таблиці «Назви брендів у Prom» вкажіть відповідність, якщо бренд на сайті пишеться інакше, ніж у довіднику Prom (під час введення з’являються підказки).', 'factorial2000-catalog-sync' ); ?></li>
							<li><strong><?php esc_html_e( 'Категорії на Prom', 'factorial2000-catalog-sync' ); ?></strong> — <?php esc_html_e( 'Prom вимагає номер своєї категорії для кожного товару. Вкажіть «Категорію Prom за замовчуванням» і/або впишіть номери в таблиці «Категорії магазину → категорії Prom»; правило для батьківської категорії діє й на вкладені. Блок «Свої правила (для досвідчених)» — той самий вибір текстом у форматі «частина шляху=номер»; спрацьовує перше правило, що збіглося.', 'factorial2000-catalog-sync' ); ?></li>
							<li><strong><?php esc_html_e( 'Код постачальника (MPN)', 'factorial2000-catalog-sync' ); ?></strong> — <?php esc_html_e( 'звідки взяти артикул постачальника для Prom. За замовчуванням це код, який плагін запам’ятовує при оновленні наявності (тег vendorCode). Можна вибрати інше поле або нічого не передавати.', 'factorial2000-catalog-sync' ); ?></li>
							<li><strong><?php esc_html_e( 'Оновлення вигрузки', 'factorial2000-catalog-sync' ); ?></strong> — <?php esc_html_e( 'розклад: вручну, щогодини, 6 / 4 / 2 рази на день або щодня.', 'factorial2000-catalog-sync' ); ?></li>
						</ul>
				</div>

				<div class="f2000cs-docs-card" id="marketplaces-rules">
						<h3><?php esc_html_e( 'Які товари потрапляють у файл', 'factorial2000-catalog-sync' ); ?></h3>
						<ul>
							<li><?php esc_html_e( 'Лише опубліковані товари, у яких є ціна, фото та категорія. Решта рахується як «пропущено».', 'factorial2000-catalog-sync' ); ?></li>
							<li><?php esc_html_e( 'Товари з розмірами й кольорами перетворюються на «різновиди» Prom: до трьох характеристик, і в кожного різновиду набір має бути унікальним. Якщо в групі два різновиди з однаковими характеристиками, Prom такий товар не прийме — групу пропущено, а її артикули показано на сторінці, щоб ви виправили характеристики.', 'factorial2000-catalog-sync' ); ?></li>
							<li><?php esc_html_e( 'Товар без залишку лишається у файлі зі статусом «немає в наявності».', 'factorial2000-catalog-sync' ); ?></li>
							<li><?php esc_html_e( 'Товар, який оновлення наявності перевело в чернетку, у файл не потрапляє (якщо не ввімкнено «Неопубліковані товари»).', 'factorial2000-catalog-sync' ); ?></li>
						</ul>
				</div>

				<div class="f2000cs-docs-card" id="marketplaces-auto">
						<h3><?php esc_html_e( 'Автоматичне оновлення вигрузки', 'factorial2000-catalog-sync' ); ?></h3>
						<ul>
							<li><?php esc_html_e( 'Файл збирається частинами, тому великі каталоги не впираються в ліміт часу сервера. Поки йде збирання, Prom бачить попередній повний файл; новий замінює його лише коли повністю готовий.', 'factorial2000-catalog-sync' ); ?></li>
							<li><strong><?php esc_html_e( 'Після оновлення наявності', 'factorial2000-catalog-sync' ); ?></strong> — <?php esc_html_e( 'якщо ввімкнено, то після кожного оновлення від постачальників (за розкладом, у фоні чи кнопкою «Одразу») вигрузка за хвилину-дві збирається заново. Prom за тим самим посиланням отримує свіжі ціни й наявність. Можна поєднувати з розкладом.', 'factorial2000-catalog-sync' ); ?></li>
							<li><?php esc_html_e( 'Для сервера є команда: wp f2000cs marketplace build prom.', 'factorial2000-catalog-sync' ); ?></li>
							<li><?php esc_html_e( 'Без активної Pro-ліцензії чи тріалу вигрузка не оновлюється. Файл, якому більше 3 днів, знімається, щоб Prom не брав застарілі ціни. Після поновлення ліцензії збирання продовжується само.', 'factorial2000-catalog-sync' ); ?></li>
						</ul>
						<div class="f2000cs-docs-note"><strong><?php esc_html_e( 'Далі:', 'factorial2000-catalog-sync' ); ?></strong> <?php esc_html_e( 'Rozetka та Hotline додаватимуться як окремі вкладки тієї ж сторінки.', 'factorial2000-catalog-sync' ); ?></div>
				</div>

				</section>

				<section id="telegram" class="f2000cs-docs-section">
					<h2 class="f2000cs-docs-section__title"><?php esc_html_e( 'Telegram-сповіщення', 'factorial2000-catalog-sync' ); ?></h2>

				<div class="f2000cs-docs-card" id="telegram-setup">
						<h3><?php esc_html_e( 'Як підключити', 'factorial2000-catalog-sync' ); ?></h3>
						<p><?php esc_html_e( 'Telegram-звіти необов’язкові: якщо поля порожні, нічого не надсилається. Доступно у Free і Pro. Плагін повідомляє про завершення оновлення наявності, імпорту та інші службові події.', 'factorial2000-catalog-sync' ); ?></p>
						<ol class="f2000cs-docs-steps">
							<li><?php esc_html_e( 'У Telegram знайдіть бота @BotFather, надішліть команду /newbot, задайте ім’я — він видасть токен (рядок виду 1234567890:ABC…).', 'factorial2000-catalog-sync' ); ?></li>
							<li><?php esc_html_e( 'Знайдіть бота @userinfobot, натисніть Start — він покаже ваш числовий ID. Для групи додайте свого бота в групу і використайте ID групи (від’ємне число).', 'factorial2000-catalog-sync' ); ?></li>
							<li><?php esc_html_e( 'На сторінці «Оновлення XML» у блоці «Telegram-сповіщення» вставте токен у «Токен бота», а ID — у «ID користувачів» (кілька через кому).', 'factorial2000-catalog-sync' ); ?></li>
							<li><?php esc_html_e( 'Обов’язково спершу напишіть своєму боту /start (або додайте його в групу) — інакше Telegram не дозволить йому писати вам.', 'factorial2000-catalog-sync' ); ?></li>
						</ol>
				</div>

				</section>

				<section id="cron" class="f2000cs-docs-section">
					<h2 class="f2000cs-docs-section__title"><?php esc_html_e( 'Cron та планування', 'factorial2000-catalog-sync' ); ?></h2>

					<div class="f2000cs-docs-card">
						<h3><?php esc_html_e( 'Як працює автооновлення', 'factorial2000-catalog-sync' ); ?></h3>
						<p><?php esc_html_e( 'Використовується WP-Cron (не системний cron сервера):', 'factorial2000-catalog-sync' ); ?></p>
						<ul>
							<li><?php esc_html_e( 'спрацьовує при відвідуваннях сайту;', 'factorial2000-catalog-sync' ); ?></li>
							<li><?php esc_html_e( 'якщо сайт довго без трафіку — завдання можуть «запізнюватись»;', 'factorial2000-catalog-sync' ); ?></li>
							<li><?php esc_html_e( 'для стабільності налаштуйте системний cron на хостингу, наприклад:', 'factorial2000-catalog-sync' ); ?> <code>*/5 * * * * wget -q -O - https://ваш-сайт.com/wp-cron.php?doing_wp_cron &gt; /dev/null 2&gt;&amp;1</code></li>
						</ul>
					</div>

					<div class="f2000cs-docs-card">
						<h3><?php esc_html_e( 'Кнопка «Зупинити cron»', 'factorial2000-catalog-sync' ); ?></h3>
						<p><?php esc_html_e( 'Знімає з черги заплановані завдання оновлення наявності (рекурентне оновлення і фонові одиночні задачі); розклад вигрузок на маркетплейси не зачіпає. Автооновлення залишається зупиненим, доки ви не запустите оновлення у фоновому режимі або не активуєте плагін повторно (саме лише збереження інтервалу cron не відновлює).', 'factorial2000-catalog-sync' ); ?></p>
					</div>

				<div class="f2000cs-docs-card" id="cron-uninstall">
						<h3><?php esc_html_e( 'Деактивація і видалення плагіна', 'factorial2000-catalog-sync' ); ?></h3>
						<ul>
							<li><?php esc_html_e( 'Деактивація зупиняє автооновлення наявності та оновлення вигрузок на маркетплейси. Налаштування лишаються.', 'factorial2000-catalog-sync' ); ?></li>
							<li><?php esc_html_e( 'Видалення плагіна прибирає його налаштування, тимчасові дані, створені XML-файли та файли вигрузок на маркетплейси. Товари WooCommerce не видаляються.', 'factorial2000-catalog-sync' ); ?></li>
						</ul>
				</div>

				</section>

				<section id="plans" class="f2000cs-docs-section">
					<h2 class="f2000cs-docs-section__title"><?php esc_html_e( 'Free і Pro', 'factorial2000-catalog-sync' ); ?></h2>

					<div class="f2000cs-docs-card">
						<h3><?php esc_html_e( 'Безкоштовний тріал', 'factorial2000-catalog-sync' ); ?></h3>
						<p><?php esc_html_e( 'Спробуйте Pro безкоштовно 14 днів — без прив’язки банківської карти. Під час тріалу в Pro-версії плагіна (її пропонує встановити Freemius) доступні всі Pro-функції; після закінчення залишається Free-план, якщо не оформити підписку.', 'factorial2000-catalog-sync' ); ?></p>
					</div>

					<div class="f2000cs-docs-card">
						<h3><?php esc_html_e( 'Free', 'factorial2000-catalog-sync' ); ?></h3>
						<ul>
							<li><?php esc_html_e( '1 постачальник;', 'factorial2000-catalog-sync' ); ?></li>
							<li><?php esc_html_e( 'імпорт товарів, режим «не змінювати ціни», Telegram, Vendor Code на товарі;', 'factorial2000-catalog-sync' ); ?></li>
							<li><?php esc_html_e( 'автооновлення раз на добу + 3 ручні запуски на добу;', 'factorial2000-catalog-sync' ); ?></li>
							<li><?php esc_html_e( 'коригування ціни (націнка / маржа / фіксована сума) доступне; без вигрузки XML і маркетплейсів, без оновлення окремих полів, без обробки зображень, без оновлення кількості та правила малої наявності.', 'factorial2000-catalog-sync' ); ?></li>
						</ul>
					</div>

					<div class="f2000cs-docs-card">
						<h3><?php esc_html_e( 'Pro / тріал', 'factorial2000-catalog-sync' ); ?></h3>
						<ul>
							<li><?php esc_html_e( 'багато постачальників, часті інтервали cron, без денного ліміту ручних запусків;', 'factorial2000-catalog-sync' ); ?></li>
							<li><?php esc_html_e( 'оновлення кількості, мала наявність варіацій;', 'factorial2000-catalog-sync' ); ?></li>
							<li><?php esc_html_e( 'оновлення окремих полів, обробка зображень при імпорті;', 'factorial2000-catalog-sync' ); ?></li>
							<li><?php esc_html_e( 'сторінка вигрузки: фільтр + редактор XML;', 'factorial2000-catalog-sync' ); ?></li>
							<li><?php esc_html_e( 'маркетплейси: вигрузка товарів сайту на Prom.ua з автооновленням.', 'factorial2000-catalog-sync' ); ?></li>
						</ul>
						<p><?php esc_html_e( 'Під час тріалу доступні ті самі можливості, що й у Pro.', 'factorial2000-catalog-sync' ); ?></p>
						<div class="f2000cs-docs-note">
							<strong><?php esc_html_e( 'Pro-збірка:', 'factorial2000-catalog-sync' ); ?></strong>
							<?php esc_html_e( 'Усі Pro-функції — кілька постачальників, часті оновлення, оновлення кількості, правило для варіативних товарів, конвертація зображень, оновлення окремих полів, фільтр і редактор вигрузок, маркетплейси — входять лише до Pro-збірки плагіна; безкоштовна версія з WordPress.org цього коду не містить. Після оформлення підписки чи тріалу встановіть Pro-версію зі сторінки акаунта Freemius (плагін покаже відповідне повідомлення).', 'factorial2000-catalog-sync' ); ?>
						</div>
					</div>
				</section>

				<section id="faq" class="f2000cs-docs-section">
					<h2 class="f2000cs-docs-section__title"><?php esc_html_e( 'Часті питання', 'factorial2000-catalog-sync' ); ?></h2>

					<div class="f2000cs-docs-card">
						<h3><?php esc_html_e( 'Чому товари не оновлюються?', 'factorial2000-catalog-sync' ); ?></h3>
						<ul>
							<li><?php esc_html_e( 'перевірте URL XML у браузері;', 'factorial2000-catalog-sync' ); ?></li>
							<li><?php esc_html_e( 'SKU-префікс має збігатися з імпортом;', 'factorial2000-catalog-sync' ); ?></li>
							<li><?php esc_html_e( 'дивіться статус cron на «Оновлення XML»;', 'factorial2000-catalog-sync' ); ?></li>
							<li><?php esc_html_e( 'у Free — чи не вичерпано денний ліміт ручного запуску;', 'factorial2000-catalog-sync' ); ?></li>
							<li><?php esc_html_e( 'на сайті з низьким трафіком налаштуйте системний cron для wp-cron.php.', 'factorial2000-catalog-sync' ); ?></li>
						</ul>
					</div>

					<div class="f2000cs-docs-card">
						<h3><?php esc_html_e( 'Чому імпорт не створює товар?', 'factorial2000-catalog-sync' ); ?></h3>
						<ul>
							<li><?php esc_html_e( 'ціна 0 або від’ємна — offer пропускається;', 'factorial2000-catalog-sync' ); ?></li>
							<li><?php esc_html_e( 'SKU з префіксом уже існує — повторно не створюється;', 'factorial2000-catalog-sync' ); ?></li>
							<li><?php esc_html_e( 'порожня назва або битий / не-YML XML.', 'factorial2000-catalog-sync' ); ?></li>
						</ul>
					</div>

					<div class="f2000cs-docs-card">
						<h3><?php esc_html_e( 'Кілька постачальників', 'factorial2000-catalog-sync' ); ?></h3>
						<p><?php esc_html_e( 'Імпортуйте кожен XML окремо з унікальним SKU-префіксом і додайте окремого постачальника (картку) на «Оновлення XML» (додаткові слоти — Pro).', 'factorial2000-catalog-sync' ); ?></p>
					</div>

					<div class="f2000cs-docs-card">
						<h3><?php esc_html_e( 'Імпорт «завис»', 'factorial2000-catalog-sync' ); ?></h3>
						<p><?php esc_html_e( 'Оновіть сторінку. Для URL часто з’явиться «Продовжити». Для файлу з ПК — почніть знову. Великі каталоги краще імпортувати за URL на стабільному хостингу.', 'factorial2000-catalog-sync' ); ?></p>
					</div>

					<div class="f2000cs-docs-card">
						<h3><?php esc_html_e( 'Чи оновлює cron описи й фото?', 'factorial2000-catalog-sync' ); ?></h3>
						<p><?php esc_html_e( 'Ні. Cron — наявність і ціни (і кількість у Pro). Описи/фото/атрибути — через «Оновлення окремих полів» на імпорті (Pro).', 'factorial2000-catalog-sync' ); ?></p>
					</div>

					<div class="f2000cs-docs-card">
						<h3><?php esc_html_e( 'WebP не конвертується', 'factorial2000-catalog-sync' ); ?></h3>
						<p><?php esc_html_e( 'Дивіться блок «Цей хостинг» на сторінці імпорту. Якщо запис у WebP / AVIF = ні — оберіть JPG або попросіть хостинг увімкнути WebP/AVIF у GD/Imagick.', 'factorial2000-catalog-sync' ); ?></p>
					</div>

					<div class="f2000cs-docs-card">
						<h3><?php esc_html_e( 'Немає меню плагіна', 'factorial2000-catalog-sync' ); ?></h3>
						<p><?php esc_html_e( 'Активуйте WooCommerce. Без нього пункт «Синхронізація каталогу» не реєструється.', 'factorial2000-catalog-sync' ); ?></p>
					</div>

				<div class="f2000cs-docs-card" id="faq-prom-missing">
						<h3><?php esc_html_e( 'Чому товару немає у вигрузці для Prom?', 'factorial2000-catalog-sync' ); ?></h3>
						<ul>
							<li><?php esc_html_e( 'Перевірте, що товар опублікований, має ціну, фото та категорію.', 'factorial2000-catalog-sync' ); ?></li>
							<li><?php esc_html_e( 'Для варіативного товару в різновидів мають бути різні характеристики (наприклад, розмір) — інакше групу пропущено, а її артикул показано на сторінці «Маркетплейси».', 'factorial2000-catalog-sync' ); ?></li>
							<li><?php esc_html_e( 'Після змін натисніть «Згенерувати зараз» або дочекайтесь наступного оновлення вигрузки.', 'factorial2000-catalog-sync' ); ?></li>
						</ul>
				</div>

				<div class="f2000cs-docs-card" id="faq-prom-categories">
						<h3><?php esc_html_e( 'Prom просить номер категорії. Де його взяти?', 'factorial2000-catalog-sync' ); ?></h3>
						<p><?php esc_html_e( 'Шукати номер вручну не потрібно: довідник категорій Prom уже вбудований у плагін. У таблиці «Категорії магазину → категорії Prom», у полі «Категорія Prom за замовчуванням» і в блоці «Prom.ua» у картці товару почніть вводити назву категорії (наприклад, «жіночі куртки») і виберіть її зі списку — номер (portal_category_id) підставиться сам.', 'factorial2000-catalog-sync' ); ?></p>
						<p><?php esc_html_e( 'Що важливіше: категорія з картки товару, далі правило для категорії магазину (або її батьківської), далі категорія за замовчуванням. Різновиди беруть категорію основного товару.', 'factorial2000-catalog-sync' ); ?></p>
				</div>

				<div class="f2000cs-docs-card" id="faq-ssl">
						<h3><?php esc_html_e( 'Не вдається завантажити файл постачальника через сертифікат', 'factorial2000-catalog-sync' ); ?></h3>
						<p><?php esc_html_e( 'Увімкніть «Слабкі SSL-сертифікати» на сторінці «Оновлення XML». Деталі — у розділі «Оновлення XML → Слабкі SSL-сертифікати».', 'factorial2000-catalog-sync' ); ?></p>
				</div>
				</section>

				<section id="developers" class="f2000cs-docs-section">
					<h2 class="f2000cs-docs-section__title"><?php esc_html_e( 'Для розробників', 'factorial2000-catalog-sync' ); ?></h2>

				<div class="f2000cs-docs-card" id="developers-hooks">
						<h3><?php esc_html_e( 'Фільтри та дії', 'factorial2000-catalog-sync' ); ?></h3>
						<ul>
							<li><strong><?php esc_html_e( 'f2000cs_ssl_verify', 'factorial2000-catalog-sync' ); ?></strong> — <?php esc_html_e( 'фільтр: true чи false — чи перевіряти SSL-сертифікати під час завантажень XML і фото.', 'factorial2000-catalog-sync' ); ?></li>
							<li><strong><?php esc_html_e( 'f2000cs_republish_returned_products', 'factorial2000-catalog-sync' ); ?></strong> — <?php esc_html_e( 'фільтр: повернути false, щоб не публікувати знову товари, які плагін сам перевів у чернетку, коли вони з’явились у файлі.', 'factorial2000-catalog-sync' ); ?></li>
							<li><strong><?php esc_html_e( 'f2000cs_stock_update_complete', 'factorial2000-catalog-sync' ); ?></strong> — <?php esc_html_e( 'дія: спрацьовує після завершення повного циклу оновлення наявності.', 'factorial2000-catalog-sync' ); ?></li>
							<li><strong><?php esc_html_e( 'f2000cs_marketplace_feeds', 'factorial2000-catalog-sync' ); ?></strong> — <?php esc_html_e( 'фільтр: список вигрузок маркетплейсів, через нього можна додати власну.', 'factorial2000-catalog-sync' ); ?></li>
							<li><strong><?php esc_html_e( 'f2000cs_marketplace_brand_taxonomies', 'factorial2000-catalog-sync' ); ?></strong> — <?php esc_html_e( 'фільтр: список таксономій, з яких береться бренд товару.', 'factorial2000-catalog-sync' ); ?></li>
						</ul>
						<p><?php esc_html_e( 'Решта хуків (інтервали, кількість) використовується всередині Pro-збірки і не є стабільним публічним API.', 'factorial2000-catalog-sync' ); ?></p>
				</div>

				</section>

			</main>
		</div>
	</div>
	<?php
}
