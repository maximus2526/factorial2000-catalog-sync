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
								— <?php esc_html_e( 'На «Оновлення XML» оберіть інтервал і запустіть оновлення один раз (або дочекайтесь WP-Cron). Далі плагін оновлюватиме наявність і ціни з XML постачальників.', 'factorial2000-catalog-sync' ); ?>
							</li>
						</ol>
					</div>

					<div class="f2000cs-docs-card">
						<h3><?php esc_html_e( 'Що таке XML постачальника?', 'factorial2000-catalog-sync' ); ?></h3>
						<p><?php esc_html_e( 'Файл у форматі YML / Prom.ua: yml_catalog → shop → currencies + categories + offers. Повний перелік полів — у розділі «Схема XML».', 'factorial2000-catalog-sync' ); ?></p>
					</div>

					<div class="f2000cs-docs-card">
						<h3><?php esc_html_e( 'Сторінки плагіна', 'factorial2000-catalog-sync' ); ?></h3>
						<ul>
							<li><strong><?php esc_html_e( 'Оновлення XML', 'factorial2000-catalog-sync' ); ?></strong> — <?php esc_html_e( 'постачальники, розклад, Telegram, ручний запуск sync, статус cron.', 'factorial2000-catalog-sync' ); ?></li>
							<li><strong><?php esc_html_e( 'Імпорт XML', 'factorial2000-catalog-sync' ); ?></strong> — <?php esc_html_e( 'імпорт товарів, оновлення окремих полів (Pro), налаштування обробки зображень (Pro).', 'factorial2000-catalog-sync' ); ?></li>
							<li><strong><?php esc_html_e( 'Налаштування вигрузки', 'factorial2000-catalog-sync' ); ?></strong> — <?php esc_html_e( 'фільтр і редактор XML (лише Pro).', 'factorial2000-catalog-sync' ); ?></li>
							<li><strong><?php esc_html_e( 'Документація', 'factorial2000-catalog-sync' ); ?></strong> — <?php esc_html_e( 'ця сторінка.', 'factorial2000-catalog-sync' ); ?></li>
						</ul>
						<div class="f2000cs-docs-note">
							<strong><?php esc_html_e( 'Потрібен WooCommerce:', 'factorial2000-catalog-sync' ); ?></strong> <?php esc_html_e( 'меню з’являється лише коли WooCommerce активний.', 'factorial2000-catalog-sync' ); ?>
						</div>
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
							<li><code>available</code> — <?php esc_html_e( 'наявність. true / 1 / yes / y → instock; false / 0 / no / n → outofstock; якщо атрибут відсутній — instock.', 'factorial2000-catalog-sync' ); ?></li>
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
							<li><code>vendorCode</code> — <?php esc_html_e( 'у метаполе f2000cs-updater-vendor, лише якщо воно ще порожнє.', 'factorial2000-catalog-sync' ); ?></li>
							<li><code>quantity</code> / <code>stock_quantity</code> — <?php esc_html_e( 'кількість на складі (Pro, опція в картці постачальника). Якщо є quantity — він має пріоритет.', 'factorial2000-catalog-sync' ); ?></li>
						</ul>
						<p><strong><?php esc_html_e( 'Додатково в «Оновленні окремих полів» (Pro)', 'factorial2000-catalog-sync' ); ?></strong></p>
						<ul>
							<li><code>short_description</code>, <code>short_description_ua</code></li>
							<li><code>keywords</code> — <?php esc_html_e( 'теги WooCommerce (роздільники «,» або «;»).', 'factorial2000-catalog-sync' ); ?></li>
							<li><code>vendorCode</code> — <?php esc_html_e( 'примусове оновлення метаполя (на відміну від stock sync).', 'factorial2000-catalog-sync' ); ?></li>
							<li><?php esc_html_e( 'а також name/description, ціни, available, quantity, picture, param, categoryId — за вибраними чекбоксами.', 'factorial2000-catalog-sync' ); ?></li>
						</ul>
						<div class="f2000cs-docs-note">
							<strong><?php esc_html_e( 'Важливо:', 'factorial2000-catalog-sync' ); ?></strong>
							<?php esc_html_e( 'vendorCode при першому імпорті (створенні товару) не записується — лише після stock update або оновлення полів. quantity при імпорті також не виставляється (_manage_stock = no).', 'factorial2000-catalog-sync' ); ?>
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
							<li><strong><?php esc_html_e( 'Не змінювати ціни…', 'factorial2000-catalog-sync' ); ?></strong> — <?php esc_html_e( 'під час stock update оновлюється лише наявність (і кількість, якщо увімкнено), ціни з XML не перезаписуються.', 'factorial2000-catalog-sync' ); ?></li>
							<li><strong><?php esc_html_e( 'Оновлювати кількість товарів', 'factorial2000-catalog-sync' ); ?></strong> <span class="f2000cs-pro-badge">Pro</span> — <?php esc_html_e( 'якщо в XML є quantity / stock_quantity — записує залишок у WooCommerce. Без опції оновлюється лише «є / немає в наявності».', 'factorial2000-catalog-sync' ); ?></li>
						</ul>
					</div>

					<div class="f2000cs-docs-card" id="suppliers-price">
						<h3><?php esc_html_e( 'Коригування ціни', 'factorial2000-catalog-sync' ); ?> <span class="f2000cs-pro-badge">Pro</span></h3>
						<p><?php esc_html_e( 'Застосовується при оновленні цін зі stock update (якщо не увімкнено «не змінювати ціни»). Три типи:', 'factorial2000-catalog-sync' ); ?></p>
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
							<li><?php esc_html_e( 'Pro: що 5 / 10 / 15 / 30 хвилин, щогодини, двічі на день, щодня.', 'factorial2000-catalog-sync' ); ?></li>
							<li><?php esc_html_e( 'Free: лише «Щодня»; частіші інтервали недоступні.', 'factorial2000-catalog-sync' ); ?></li>
						</ul>
						<div class="f2000cs-docs-note">
							<strong><?php esc_html_e( 'Free:', 'factorial2000-catalog-sync' ); ?></strong> <?php esc_html_e( 'також не більше 1 ручного запуску оновлення на добу.', 'factorial2000-catalog-sync' ); ?>
						</div>
					</div>

					<div class="f2000cs-docs-card" id="update-prices">
						<h3><?php esc_html_e( 'Що оновлює stock update', 'factorial2000-catalog-sync' ); ?></h3>
						<ul>
							<li><?php esc_html_e( 'Наявність (instock / outofstock) за атрибутом available у offer.', 'factorial2000-catalog-sync' ); ?></li>
							<li><?php esc_html_e( 'Ціни (якщо не увімкнено «не змінювати ціни»), з урахуванням коригування Pro.', 'factorial2000-catalog-sync' ); ?></li>
							<li><?php esc_html_e( 'Кількість на складі — Pro-опція; теги quantity або stock_quantity.', 'factorial2000-catalog-sync' ); ?></li>
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
						</ul>
						<p><?php esc_html_e( 'Важливо: XML має містити повний каталог. Неповний фід може масово сховати прості товари.', 'factorial2000-catalog-sync' ); ?></p>
						<div class="f2000cs-docs-note f2000cs-docs-note--warn">
							<strong><?php esc_html_e( 'Увага:', 'factorial2000-catalog-sync' ); ?></strong> <?php esc_html_e( 'Якщо XML тимчасово неповний або недоступний — частина товарів може піти в чернетку. Переконайтеся, що фід завжди містить повний асортимент.', 'factorial2000-catalog-sync' ); ?>
						</div>
					</div>

					<div class="f2000cs-docs-card" id="update-variable-low">
						<h3><?php esc_html_e( 'Мала наявність варіацій', 'factorial2000-catalog-sync' ); ?> <span class="f2000cs-pro-badge">Pro</span></h3>
						<p><?php esc_html_e( 'Після stock update можна автоматично позначати variable-товар як «немає в наявності», якщо кількість варіацій «в наявності» не більша за поріг (за замовчуванням 2). Корисно, щоб у рекламі не крутились товари з майже порожньою сіткою розмірів.', 'factorial2000-catalog-sync' ); ?></p>
					</div>

					<div class="f2000cs-docs-card" id="update-vendor">
						<h3><?php esc_html_e( 'Vendor Code на товарі', 'factorial2000-catalog-sync' ); ?></h3>
						<p><?php esc_html_e( 'Опція «Показувати vendorCode адміну на сторінці товару» (Оновлення XML → Розклад і правила). Якщо увімкнено — адміністратор (manage_options) бачить у підвалі сторінки товару код з метаполя f2000cs-updater-vendor і може скопіювати кліком. Покупці блок не бачать. За замовчуванням увімкнено.', 'factorial2000-catalog-sync' ); ?></p>
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
						<p><?php esc_html_e( 'Обов’язково вкажіть SKU-префікс (той самий, що в картці постачальника). Опційно — додавати нові товари в категорію «Новинки».', 'factorial2000-catalog-sync' ); ?></p>
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
						<p><?php esc_html_e( 'Товар із уже існуючим SKU (з префіксом) повторно не створюється. vendorCode і quantity при створенні не пишуться — див. «Схема XML».', 'factorial2000-catalog-sync' ); ?></p>
					</div>

					<div class="f2000cs-docs-card" id="import-variable">
						<h3><?php esc_html_e( 'Варіативні товари', 'factorial2000-catalog-sync' ); ?></h3>
						<p><?php esc_html_e( 'Режим «Варіативні продукти»: натисніть «Проаналізувати XML». Плагін знайде групи за group_id з 2+ offers, покаже атрибути (з unit, як при імпорті) і позначить ті, що варіюються. Оберіть атрибути й запустіть імпорт.', 'factorial2000-catalog-sync' ); ?></p>
						<ul>
							<li><?php esc_html_e( 'SKU батька = префікс + group_id; SKU варіації = префікс + offer id.', 'factorial2000-catalog-sync' ); ?></li>
							<li><?php esc_html_e( 'Створюється рівно одна WC-варіація на кожен offer у групі (не декартовий добуток атрибутів).', 'factorial2000-catalog-sync' ); ?></li>
							<li><?php esc_html_e( 'Назва/опис/категорії/фото батька беруться з першого offer групи (назва — спільна база з titles).', 'factorial2000-catalog-sync' ); ?></li>
							<li><?php esc_html_e( 'Offers без group_id і групи з 1 offer у цьому режимі пропускаються.', 'factorial2000-catalog-sync' ); ?></li>
							<li><?php esc_html_e( 'Якщо вибір атрибутів «мертвий», плагін спробує auto-detect (пріоритет: розмір → колір → перший варіативний).', 'factorial2000-catalog-sync' ); ?></li>
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
							<li><strong><?php esc_html_e( 'PNG → WebP / JPG', 'factorial2000-catalog-sync' ); ?></strong> — <?php esc_html_e( 'конвертація PNG через Imagick/GD WordPress. JPG сумісніший; WebP — якщо хостинг підтримує. При помилці лишається оригінал.', 'factorial2000-catalog-sync' ); ?></li>
							<li><strong><?php esc_html_e( 'Оптимізація', 'factorial2000-catalog-sync' ); ?></strong> — <?php esc_html_e( 'перезбереження з обраною якістю.', 'factorial2000-catalog-sync' ); ?></li>
							<li><strong><?php esc_html_e( 'Якість', 'factorial2000-catalog-sync' ); ?></strong> — <?php esc_html_e( '40–100 для оптимізації/конвертації.', 'factorial2000-catalog-sync' ); ?></li>
							<li><strong><?php esc_html_e( 'Макс. сторона', 'factorial2000-catalog-sync' ); ?></strong> — <?php esc_html_e( 'зменшення зі збереженням пропорцій; 0 = без ліміту.', 'factorial2000-catalog-sync' ); ?></li>
							<li><strong><?php esc_html_e( 'Цей хостинг', 'factorial2000-catalog-sync' ); ?></strong> — <?php esc_html_e( 'перевірка Imagick, GD, JPG, WebP і resize на сервері.', 'factorial2000-catalog-sync' ); ?></li>
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

				<section id="telegram" class="f2000cs-docs-section">
					<h2 class="f2000cs-docs-section__title"><?php esc_html_e( 'Telegram-сповіщення', 'factorial2000-catalog-sync' ); ?></h2>

					<div class="f2000cs-docs-card">
						<p><?php esc_html_e( 'На сторінці «Оновлення XML» можна вказати токен бота (@BotFather) і ID чатів (@userinfobot, через кому). Спершу напишіть боту /start.', 'factorial2000-catalog-sync' ); ?></p>
						<p><?php esc_html_e( 'Плагін надсилає повідомлення про завершення оновлення стоку та інші службові події (за наявності токена й ID). Доступно у Free і Pro.', 'factorial2000-catalog-sync' ); ?></p>
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
						<p><?php esc_html_e( 'Знімає з черги заплановані завдання плагіна (рекурентне оновлення і фонові одиночні задачі). Автооновлення зупиняється. Щоб знову запланувати — запустіть оновлення вручну або дочекайтесь реакції плагіна на запуск sync / активацію (лише збереження інтервалу без вже активного розкладу може не відновити cron).', 'factorial2000-catalog-sync' ); ?></p>
					</div>
				</section>

				<section id="plans" class="f2000cs-docs-section">
					<h2 class="f2000cs-docs-section__title"><?php esc_html_e( 'Free і Pro', 'factorial2000-catalog-sync' ); ?></h2>

					<div class="f2000cs-docs-card">
						<h3><?php esc_html_e( 'Безкоштовний тріал', 'factorial2000-catalog-sync' ); ?></h3>
						<p><?php esc_html_e( 'Спробуйте Pro безкоштовно 14 днів — без прив’язки банківської карти. Під час тріалу доступні всі Pro-функції; після закінчення залишається Free-план, якщо не оформити підписку.', 'factorial2000-catalog-sync' ); ?></p>
					</div>

					<div class="f2000cs-docs-card">
						<h3><?php esc_html_e( 'Free', 'factorial2000-catalog-sync' ); ?></h3>
						<ul>
							<li><?php esc_html_e( '1 слот нового постачальника;', 'factorial2000-catalog-sync' ); ?></li>
							<li><?php esc_html_e( 'імпорт товарів, skip price, Telegram, Vendor Code на товарі;', 'factorial2000-catalog-sync' ); ?></li>
							<li><?php esc_html_e( 'автооновлення раз на добу + 1 ручний запуск на добу;', 'factorial2000-catalog-sync' ); ?></li>
							<li><?php esc_html_e( 'без вигрузки, без оновлення окремих полів, без обробки зображень, без коригування ціни / кількості / правила малої наявності.', 'factorial2000-catalog-sync' ); ?></li>
						</ul>
					</div>

					<div class="f2000cs-docs-card">
						<h3><?php esc_html_e( 'Pro / тріал', 'factorial2000-catalog-sync' ); ?></h3>
						<ul>
							<li><?php esc_html_e( 'багато постачальників, часті інтервали cron, без денного ліміту ручних запусків;', 'factorial2000-catalog-sync' ); ?></li>
							<li><?php esc_html_e( 'коригування цін, оновлення кількості, мала наявність варіацій;', 'factorial2000-catalog-sync' ); ?></li>
							<li><?php esc_html_e( 'оновлення окремих полів, обробка зображень при імпорті;', 'factorial2000-catalog-sync' ); ?></li>
							<li><?php esc_html_e( 'сторінка вигрузки: фільтр + редактор XML.', 'factorial2000-catalog-sync' ); ?></li>
						</ul>
						<p><?php esc_html_e( 'Під час тріалу доступні ті самі можливості, що й у Pro.', 'factorial2000-catalog-sync' ); ?></p>
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
						<p><?php esc_html_e( 'Імпортуйте кожен XML окремо з унікальним SKU-префіксом і додайте відповідний слот на «Оновлення XML» (додаткові слоти — Pro).', 'factorial2000-catalog-sync' ); ?></p>
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
						<p><?php esc_html_e( 'Дивіться блок «Цей хостинг» на сторінці імпорту. Якщо PNG → WebP = ні — оберіть PNG → JPG або попросіть хостинг увімкнути WebP у GD/Imagick.', 'factorial2000-catalog-sync' ); ?></p>
					</div>

					<div class="f2000cs-docs-card">
						<h3><?php esc_html_e( 'Немає меню плагіна', 'factorial2000-catalog-sync' ); ?></h3>
						<p><?php esc_html_e( 'Активуйте WooCommerce. Без нього пункт «Синхронізація каталогу» не реєструється.', 'factorial2000-catalog-sync' ); ?></p>
					</div>
				</section>

			</main>
		</div>
	</div>
	<?php
}
