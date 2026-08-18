<?php
/**
 * Import XML admin page and AJAX handlers.
 *
 * @package Factorial2000_Catalog_Sync
 */

defined( 'ABSPATH' ) || exit;

function f2000cs_import_page() {
	$is_pro      = function_exists( 'f2000cs_is_pro' ) ? f2000cs_is_pro() : true;
	$last        = f2000cs_get_last_import_prefs();
	$last_url    = $last['xml_url'];
	$last_prefix = $last['sku_prefix'];
	if ( '' === $last_prefix ) {
		$last_prefix = (string) get_option( 'f2000cs_sku_prefix_1', '' );
	}
	?>
	<div class="wrap f2000cs-import-page">
		<?php f2000cs_render_admin_page_title( __( 'Factorial2000 Catalog Sync – Імпорт', 'factorial2000-catalog-sync' ) ); ?>

		<?php f2000cs_render_import_resume_notice(); ?>
		<?php settings_errors(); ?>

		<div class="f2000cs-import-page__layout">
			<div class="f2000cs-import-page__main">
				<form id="xml-import-form" class="f2000cs-import-page__form" enctype="multipart/form-data">
					<?php wp_nonce_field( 'f2000cs_import_action', 'f2000cs_import_nonce' ); ?>

					<section class="f2000cs-settings-panel">
						<header class="f2000cs-settings-panel__head">
							<h2 class="f2000cs-settings-panel__title"><?php esc_html_e( 'Джерело XML', 'factorial2000-catalog-sync' ); ?></h2>
						</header>
						<div class="f2000cs-settings-panel__intro">
							<p class="description"><?php esc_html_e( 'Завантажте фід з URL або з комп’ютера та вкажіть унікальний SKU-префікс для цього джерела.', 'factorial2000-catalog-sync' ); ?></p>
						</div>
						<div class="f2000cs-settings-panel__body">
							<div class="f2000cs-import-source-grid">
								<div class="f2000cs-import-source">
									<span class="f2000cs-import-source__label"><?php esc_html_e( 'Тип джерела', 'factorial2000-catalog-sync' ); ?></span>
									<label class="f2000cs-import-source__option">
										<input type="radio" name="import_source" value="url" checked>
										<span>
											<strong><?php esc_html_e( 'URL', 'factorial2000-catalog-sync' ); ?></strong>
											<small><?php esc_html_e( 'Пряме посилання на XML/YML', 'factorial2000-catalog-sync' ); ?></small>
										</span>
									</label>
									<label class="f2000cs-import-source__option">
										<input type="radio" name="import_source" value="file">
										<span>
											<strong><?php esc_html_e( 'Файл', 'factorial2000-catalog-sync' ); ?></strong>
											<small><?php esc_html_e( 'Завантажити .xml з комп’ютера', 'factorial2000-catalog-sync' ); ?></small>
										</span>
									</label>
								</div>

								<div class="f2000cs-import-source-fields">
									<div id="import-source-url-row" class="f2000cs-import-field-block">
										<label for="import_xml_url"><?php esc_html_e( 'URL XML файлу', 'factorial2000-catalog-sync' ); ?></label>
										<input type="url" name="import_xml_url" id="import_xml_url" class="regular-text" value="<?php echo esc_attr( $last_url ); ?>" placeholder="<?php esc_attr_e( 'https://example.com/products.xml', 'factorial2000-catalog-sync' ); ?>">
										<p class="description"><?php esc_html_e( 'Пряме посилання на XML/YML вигрузку Prom або постачальника.', 'factorial2000-catalog-sync' ); ?></p>
									</div>
									<div id="import-source-file-row" class="f2000cs-import-field-block is-hidden" hidden>
										<label for="import_xml_file"><?php esc_html_e( 'XML файл', 'factorial2000-catalog-sync' ); ?></label>
										<input type="file" name="import_xml_file" id="import_xml_file" accept=".xml,text/xml,application/xml">
									</div>
									<div class="f2000cs-import-field-block">
										<label for="import_sku_prefix"><?php esc_html_e( 'SKU Prefix', 'factorial2000-catalog-sync' ); ?></label>
										<input type="text" name="import_sku_prefix" id="import_sku_prefix" class="regular-text" value="<?php echo esc_attr( $last_prefix ); ?>" placeholder="<?php esc_attr_e( 'Наприклад: NEW_', 'factorial2000-catalog-sync' ); ?>" required>
										<p class="description"><?php esc_html_e( 'Унікальний префікс, щоб не змішати артикули різних постачальників.', 'factorial2000-catalog-sync' ); ?></p>
									</div>
								</div>
							</div>
						</div>
					</section>

					<section class="f2000cs-settings-panel f2000cs-import-work">
						<header class="f2000cs-settings-panel__head f2000cs-import-work__head">
							<h2 class="f2000cs-settings-panel__title"><?php esc_html_e( 'Дія з XML', 'factorial2000-catalog-sync' ); ?></h2>
							<div class="f2000cs-import-tabs" role="tablist" aria-label="<?php esc_attr_e( 'Режим роботи з XML', 'factorial2000-catalog-sync' ); ?>">
								<button
									type="button"
									class="f2000cs-import-tabs__tab is-active"
									role="tab"
									id="f2000cs-tab-import"
									aria-selected="true"
									aria-controls="f2000cs-panel-import"
									data-f2000cs-tab="import"
								>
									<?php esc_html_e( 'Імпортувати', 'factorial2000-catalog-sync' ); ?>
								</button>
								<button
									type="button"
									class="f2000cs-import-tabs__tab"
									role="tab"
									id="f2000cs-tab-update-fields"
									aria-selected="false"
									aria-controls="f2000cs-panel-update-fields"
									data-f2000cs-tab="update-fields"
								>
									<?php esc_html_e( 'Оновити окремі поля', 'factorial2000-catalog-sync' ); ?>
									<?php if ( ! $is_pro ) : ?>
										<span class="f2000cs-pro-badge"><?php esc_html_e( 'Pro', 'factorial2000-catalog-sync' ); ?></span>
									<?php endif; ?>
								</button>
							</div>
						</header>

						<div class="f2000cs-settings-panel__body">
							<div
								class="f2000cs-import-tab-panel"
								id="f2000cs-panel-import"
								role="tabpanel"
								aria-labelledby="f2000cs-tab-import"
								data-f2000cs-panel="import"
							>
								<div class="f2000cs-import-field">
									<label for="new_category">
										<input type="checkbox" name="new_category" id="new_category" value="1">
										<?php esc_html_e( 'Додавати нові товари в категорію «Новинки»', 'factorial2000-catalog-sync' ); ?>
									</label>
								</div>

								<div class="f2000cs-import-mode">
									<span class="f2000cs-import-mode__title"><?php esc_html_e( 'Режим імпорту', 'factorial2000-catalog-sync' ); ?></span>
									<div class="f2000cs-import-mode__options">
										<label class="f2000cs-import-mode__option">
											<input type="radio" name="import_mode" value="simple" checked>
											<span>
												<strong><?php esc_html_e( 'Прості продукти', 'factorial2000-catalog-sync' ); ?></strong>
												<small><?php esc_html_e( 'Без group_id або групи з 1 offer', 'factorial2000-catalog-sync' ); ?></small>
											</span>
										</label>
										<label class="f2000cs-import-mode__option">
											<input type="radio" name="import_mode" value="variable">
											<span>
												<strong><?php esc_html_e( 'Варіативні продукти', 'factorial2000-catalog-sync' ); ?></strong>
												<small><?php esc_html_e( 'Групи group_id з 2+ offers + вибір атрибутів', 'factorial2000-catalog-sync' ); ?></small>
											</span>
										</label>
									</div>
								</div>

								<div class="f2000cs-import-actions">
									<button type="button" id="analyze-xml" class="button button-secondary is-hidden">
										<?php esc_html_e( 'Проаналізувати XML', 'factorial2000-catalog-sync' ); ?>
									</button>
									<button type="button" id="start-import" class="button button-primary">
										<?php esc_html_e( 'Імпортувати', 'factorial2000-catalog-sync' ); ?>
									</button>
									<button type="button" id="stop-import" class="button button-secondary is-hidden">
										<?php esc_html_e( 'Зупинити', 'factorial2000-catalog-sync' ); ?>
									</button>
								</div>
							</div>

							<div
								class="f2000cs-import-tab-panel is-hidden"
								id="f2000cs-panel-update-fields"
								role="tabpanel"
								aria-labelledby="f2000cs-tab-update-fields"
								data-f2000cs-panel="update-fields"
								hidden
							>
								<div class="f2000cs-update-fields">
									<p class="f2000cs-update-fields__title">
										<strong><?php esc_html_e( 'Інформація, яку потрібно оновити', 'factorial2000-catalog-sync' ); ?></strong>
									</p>
									<p class="description f2000cs-update-fields__hint">
										<?php esc_html_e( 'Якщо за заданим SKU (з урахуванням Prefix) товар уже існує — у ньому буде оновлена / додана обрана нижче інформація з XML.', 'factorial2000-catalog-sync' ); ?>
									</p>
									<div
										class="f2000cs-update-fields__list<?php echo $is_pro ? '' : ' f2000cs-update-fields__list--locked'; ?>"
										<?php echo $is_pro ? '' : 'data-pro-tip="' . esc_attr__( 'Відкривається в Pro версії', 'factorial2000-catalog-sync' ) . '"'; ?>
									>
										<label class="f2000cs-update-fields__option f2000cs-update-fields__option--all">
											<input type="checkbox" id="update_fields_select_all" value="all" <?php disabled( ! $is_pro ); ?>>
											<?php esc_html_e( 'Вибрати всі', 'factorial2000-catalog-sync' ); ?>
										</label>
										<label class="f2000cs-update-fields__option">
											<input type="checkbox" name="update_fields[]" value="name" class="f2000cs-update-field" <?php disabled( ! $is_pro ); ?>>
											<?php esc_html_e( 'Назва', 'factorial2000-catalog-sync' ); ?>
										</label>
										<label class="f2000cs-update-fields__option">
											<input type="checkbox" name="update_fields[]" value="description" class="f2000cs-update-field" <?php disabled( ! $is_pro ); ?>>
											<?php esc_html_e( 'Опис', 'factorial2000-catalog-sync' ); ?>
										</label>
										<label class="f2000cs-update-fields__option">
											<input type="checkbox" name="update_fields[]" value="short_description" class="f2000cs-update-field" <?php disabled( ! $is_pro ); ?>>
											<?php esc_html_e( 'Короткий опис', 'factorial2000-catalog-sync' ); ?>
											<span class="f2000cs-update-fields__note">
												<?php esc_html_e( '(тільки якщо в XML є тег short_description)', 'factorial2000-catalog-sync' ); ?>
											</span>
										</label>
										<label class="f2000cs-update-fields__option">
											<input type="checkbox" name="update_fields[]" value="tags" class="f2000cs-update-field" <?php disabled( ! $is_pro ); ?>>
											<?php esc_html_e( 'Теги товару', 'factorial2000-catalog-sync' ); ?>
											<span class="f2000cs-update-fields__note">
												<?php esc_html_e( '(ключові запити з Prom)', 'factorial2000-catalog-sync' ); ?>
											</span>
										</label>
										<label class="f2000cs-update-fields__option">
											<input type="checkbox" name="update_fields[]" value="price" class="f2000cs-update-field" <?php disabled( ! $is_pro ); ?>>
											<?php esc_html_e( 'Ціна', 'factorial2000-catalog-sync' ); ?>
										</label>
										<label class="f2000cs-update-fields__option">
											<input type="checkbox" name="update_fields[]" value="oldprice" class="f2000cs-update-field" <?php disabled( ! $is_pro ); ?>>
											<?php esc_html_e( 'Стара ціна', 'factorial2000-catalog-sync' ); ?>
										</label>
										<label class="f2000cs-update-fields__option">
											<input type="checkbox" name="update_fields[]" value="status" class="f2000cs-update-field" <?php disabled( ! $is_pro ); ?>>
											<?php esc_html_e( 'Статус', 'factorial2000-catalog-sync' ); ?>
										</label>
										<label class="f2000cs-update-fields__option">
											<input type="checkbox" name="update_fields[]" value="stock_quantity" class="f2000cs-update-field" <?php disabled( ! $is_pro ); ?>>
											<?php esc_html_e( 'Кількість на складі', 'factorial2000-catalog-sync' ); ?>
										</label>
										<label class="f2000cs-update-fields__option">
											<input type="checkbox" name="update_fields[]" value="images" class="f2000cs-update-field" <?php disabled( ! $is_pro ); ?>>
											<?php esc_html_e( 'Фото', 'factorial2000-catalog-sync' ); ?>
										</label>
										<label class="f2000cs-update-fields__option">
											<input type="checkbox" name="update_fields[]" value="attributes" class="f2000cs-update-field" <?php disabled( ! $is_pro ); ?>>
											<?php esc_html_e( 'Атрибути / характеристики', 'factorial2000-catalog-sync' ); ?>
										</label>
										<label class="f2000cs-update-fields__option">
											<input type="checkbox" name="update_fields[]" value="categories" class="f2000cs-update-field" <?php disabled( ! $is_pro ); ?>>
											<?php esc_html_e( 'Категорії', 'factorial2000-catalog-sync' ); ?>
										</label>
										<label class="f2000cs-update-fields__option">
											<input type="checkbox" name="update_fields[]" value="vendorCode" class="f2000cs-update-field" <?php disabled( ! $is_pro ); ?>>
											<?php esc_html_e( 'Код постачальника (vendorCode)', 'factorial2000-catalog-sync' ); ?>
											<span class="f2000cs-update-fields__note">
												<?php esc_html_e( '(з XML; показується адміну на сторінці товару для копіювання)', 'factorial2000-catalog-sync' ); ?>
											</span>
										</label>
									</div>
								</div>

								<div class="f2000cs-import-actions">
									<button type="button" id="start-update-fields" class="button button-primary" <?php disabled( ! $is_pro ); ?>>
										<?php esc_html_e( 'Оновити', 'factorial2000-catalog-sync' ); ?>
										<?php if ( ! $is_pro ) : ?>
											<span class="f2000cs-pro-badge"><?php esc_html_e( 'Pro', 'factorial2000-catalog-sync' ); ?></span>
										<?php endif; ?>
									</button>
									<button type="button" id="stop-update-fields" class="button button-secondary is-hidden">
										<?php esc_html_e( 'Зупинити', 'factorial2000-catalog-sync' ); ?>
									</button>
								</div>
							</div>
						</div>
					</section>
				</form>

				<section id="groups-analysis-container" class="f2000cs-settings-panel f2000cs-analysis">
					<header class="f2000cs-settings-panel__head">
						<h2 class="f2000cs-settings-panel__title"><?php esc_html_e( 'Вибір варіаційних атрибутів', 'factorial2000-catalog-sync' ); ?></h2>
					</header>
					<div class="f2000cs-settings-panel__intro">
						<p class="description"><?php esc_html_e( 'Для кожної групи товарів виберіть атрибут, який буде використовуватись для створення варіацій.', 'factorial2000-catalog-sync' ); ?></p>
					</div>
					<div class="f2000cs-settings-panel__body">
						<div id="analysis-status" class="f2000cs-analysis__status"></div>
						<div id="groups-list" class="f2000cs-analysis__list"></div>
						<button type="button" id="start-import-with-selection" class="button button-primary f2000cs-analysis__submit is-hidden">
							<?php esc_html_e( 'Імпортувати з вибраними атрибутами', 'factorial2000-catalog-sync' ); ?>
						</button>
					</div>
				</section>

				<section id="import-progress-container" class="f2000cs-settings-panel f2000cs-import-progress" hidden>
					<header class="f2000cs-settings-panel__head f2000cs-import-progress__head">
						<h2 class="f2000cs-settings-panel__title"><?php esc_html_e( 'Прогрес імпорту', 'factorial2000-catalog-sync' ); ?></h2>
						<span id="import-progress-percent" class="f2000cs-import-progress__percent" aria-live="polite">0%</span>
					</header>
					<div class="f2000cs-settings-panel__body f2000cs-import-progress__body">
						<div class="f2000cs-import-progress__track" role="presentation">
							<progress id="import-progress" class="f2000cs-import-progress__bar" value="0" max="100"></progress>
						</div>
						<p id="import-status" class="f2000cs-import-progress__status" aria-live="polite"></p>
					</div>
				</section>
			</div>
		</div>

		<?php f2000cs_render_import_images_panel(); ?>

		<section class="f2000cs-settings-panel f2000cs-import-guide">
			<header class="f2000cs-settings-panel__head">
				<h2 class="f2000cs-settings-panel__title"><?php esc_html_e( 'Як це працює', 'factorial2000-catalog-sync' ); ?></h2>
			</header>
			<div class="f2000cs-settings-panel__body">
				<p>
					<strong><?php esc_html_e( 'Які файли можна імпортувати?', 'factorial2000-catalog-sync' ); ?></strong>
					<?php esc_html_e( 'Не лише власну вигрузку Prom.ua — також XML-фіди постачальників у форматі YML / XML-прайс.', 'factorial2000-catalog-sync' ); ?>
				</p>
				<p>
					<strong><?php esc_html_e( 'Формат', 'factorial2000-catalog-sync' ); ?></strong>
					<?php esc_html_e( 'yml_catalog → shop → categories + offers. Кожен товар — тег offer.', 'factorial2000-catalog-sync' ); ?>
				</p>

				<details class="f2000cs-import-guide__details">
					<summary><?php esc_html_e( 'Що читає плагін з offer', 'factorial2000-catalog-sync' ); ?></summary>
					<p class="description"><?php esc_html_e( 'Атрибути offer:', 'factorial2000-catalog-sync' ); ?></p>
					<ul class="f2000cs-import-guide__list">
						<li><code>id</code> — <?php esc_html_e( 'артикул; у WooCommerce стає SKU з префіксом.', 'factorial2000-catalog-sync' ); ?></li>
						<li><code>available</code> — <?php esc_html_e( 'наявність: true / 1 / yes → instock, інакше outofstock.', 'factorial2000-catalog-sync' ); ?></li>
						<li><code>group_id</code> — <?php esc_html_e( 'група варіацій (режим «Варіативні», 2+ offers). У режимі «Прості» без group_id або з 1 offer → simple.', 'factorial2000-catalog-sync' ); ?></li>
					</ul>
					<p class="description"><?php esc_html_e( 'Дочірні теги при імпорті:', 'factorial2000-catalog-sync' ); ?></p>
					<ul class="f2000cs-import-guide__list">
						<li><code>name</code> / <code>name_ua</code> — <?php esc_html_e( 'назва (якщо є name_ua — пріоритет у неї).', 'factorial2000-catalog-sync' ); ?></li>
						<li><code>description</code> / <code>description_ua</code> — <?php esc_html_e( 'опис (аналогічно з пріоритетом *_ua).', 'factorial2000-catalog-sync' ); ?></li>
						<li><code>price</code>, <code>oldprice</code> — <?php esc_html_e( 'ціна та стара ціна; price має бути > 0 (інакше offer / варіація пропускається).', 'factorial2000-catalog-sync' ); ?></li>
						<li><code>currencyId</code> — <?php esc_html_e( 'валюта offer; конвертація за курсами з &lt;currencies&gt;.', 'factorial2000-catalog-sync' ); ?></li>
						<li><code>categoryId</code> — <?php esc_html_e( 'одна або кілька категорій з XML.', 'factorial2000-catalog-sync' ); ?></li>
						<li><code>picture</code> — <?php esc_html_e( 'усі URL зображень (обкладинка + галерея).', 'factorial2000-catalog-sync' ); ?></li>
						<li><code>param</code> — <?php esc_html_e( 'атрибути; атрибут unit додається до значення; кілька однакових name об’єднуються через «;».', 'factorial2000-catalog-sync' ); ?></li>
						<li><code>vendor</code> — <?php esc_html_e( 'виробник → атрибут «Виробник», якщо такого ще немає.', 'factorial2000-catalog-sync' ); ?></li>
						<li><code>weight</code> — <?php esc_html_e( 'вага → метаполе _weight (якщо > 0).', 'factorial2000-catalog-sync' ); ?></li>
						<li><code>barcode</code> — <?php esc_html_e( 'штрихкод → метаполе _barcode.', 'factorial2000-catalog-sync' ); ?></li>
						<li><code>dimensions</code> — <?php esc_html_e( 'габарити рядком → метаполе _dimensions.', 'factorial2000-catalog-sync' ); ?></li>
					</ul>
					<p class="description"><?php esc_html_e( 'Також у фіді (не лише при створенні товару):', 'factorial2000-catalog-sync' ); ?></p>
					<ul class="f2000cs-import-guide__list">
						<li><code>vendorCode</code> — <?php esc_html_e( 'код постачальника: пишеться при оновленні стоку / оновленні полів (Pro), не при першому імпорті.', 'factorial2000-catalog-sync' ); ?></li>
						<li><code>quantity</code> / <code>stock_quantity</code> — <?php esc_html_e( 'кількість на складі при sync і оновленні полів (Pro).', 'factorial2000-catalog-sync' ); ?></li>
						<li><code>short_description</code> / <code>keywords</code> — <?php esc_html_e( 'короткий опис і теги лише в «Оновленні окремих полів» (Pro).', 'factorial2000-catalog-sync' ); ?></li>
					</ul>
					<p><strong><?php esc_html_e( 'Приклад повної схеми:', 'factorial2000-catalog-sync' ); ?></strong></p>
					<pre class="f2000cs-import-guide__code">&lt;yml_catalog date="2026-08-01 12:00"&gt;
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
			&lt;offer id="1001" available="true" group_id="1000"&gt;
				&lt;name&gt;Кросівки&lt;/name&gt;
				&lt;name_ua&gt;Кросівки&lt;/name_ua&gt;
				&lt;description&gt;Опис&lt;/description&gt;
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
				&lt;param name="Розмір" unit="EU"&gt;42&lt;/param&gt;
			&lt;/offer&gt;
		&lt;/offers&gt;
	&lt;/shop&gt;
&lt;/yml_catalog&gt;</pre>
					<ol class="f2000cs-import-guide__steps">
						<li><?php esc_html_e( 'Завантажте .xml від Prom або прямий фід постачальника.', 'factorial2000-catalog-sync' ); ?></li>
						<li><?php esc_html_e( 'Вкажіть унікальний SKU Prefix для цього джерела.', 'factorial2000-catalog-sync' ); ?></li>
						<li><?php esc_html_e( 'Оберіть режим: прості (без group_id / 1 offer) або варіативні («Проаналізувати XML», групи з 2+ offers).', 'factorial2000-catalog-sync' ); ?></li>
						<li><?php esc_html_e( 'Після імпорту підключіть той самий URL у «Оновлення XML».', 'factorial2000-catalog-sync' ); ?></li>
					</ol>
				</details>
			</div>
		</section>
	</div>
	<?php
}

/**
 * User meta key for last import URL / SKU prefix.
 */
const F2000CS_LAST_IMPORT_META = 'f2000cs_last_import';

/**
 * Last import URL and SKU prefix for the current admin.
 *
 * @return array{xml_url:string,sku_prefix:string}
 */
function f2000cs_get_last_import_prefs(): array {
	$user_id = get_current_user_id();
	$raw     = $user_id ? get_user_meta( $user_id, F2000CS_LAST_IMPORT_META, true ) : array();
	if ( ! is_array( $raw ) ) {
		$raw = array();
	}

	$url    = isset( $raw['xml_url'] ) ? esc_url_raw( (string) $raw['xml_url'] ) : '';
	$prefix = isset( $raw['sku_prefix'] ) ? sanitize_text_field( (string) $raw['sku_prefix'] ) : '';

	if ( $url && ! preg_match( '#^https?://#i', $url ) ) {
		$url = '';
	}

	return array(
		'xml_url'    => $url,
		'sku_prefix' => $prefix,
	);
}

/**
 * Persist last import URL and SKU prefix for the current admin.
 *
 * Empty fields keep the previously stored value so a file-source import
 * does not wipe a remembered URL.
 *
 * @param string $xml_url    Remote XML URL (optional).
 * @param string $sku_prefix SKU prefix (optional).
 * @return void
 */
function f2000cs_save_last_import_prefs( string $xml_url, string $sku_prefix ): void {
	$user_id = get_current_user_id();
	if ( ! $user_id ) {
		return;
	}

	$current = f2000cs_get_last_import_prefs();
	$url     = $xml_url ? esc_url_raw( $xml_url ) : $current['xml_url'];
	$prefix  = '' !== $sku_prefix ? sanitize_text_field( $sku_prefix ) : $current['sku_prefix'];

	if ( $url && ! preg_match( '#^https?://#i', $url ) ) {
		$url = $current['xml_url'];
	}

	if ( $url === $current['xml_url'] && $prefix === $current['sku_prefix'] ) {
		return;
	}

	update_user_meta(
		$user_id,
		F2000CS_LAST_IMPORT_META,
		array(
			'xml_url'    => $url,
			'sku_prefix' => $prefix,
		)
	);
}

/**
 * Remember URL / SKU from the current import AJAX request.
 *
 * @return void
 */
function f2000cs_remember_last_import_from_request(): void {
	// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Called only after nonce verification in AJAX handlers.
	$source = isset( $_POST['import_source'] ) ? sanitize_text_field( wp_unslash( $_POST['import_source'] ) ) : '';
	// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Same as above.
	$raw_url = isset( $_POST['import_xml_url'] ) ? esc_url_raw( wp_unslash( $_POST['import_xml_url'] ) ) : '';
	// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Same as above.
	$prefix = isset( $_POST['sku_prefix'] ) ? sanitize_text_field( wp_unslash( $_POST['sku_prefix'] ) ) : '';
	$url    = ( 'url' === $source ) ? $raw_url : '';

	f2000cs_save_last_import_prefs( $url, $prefix );
}

/**
 * Resolve local XML path for import/analyze from uploaded file or remote URL.
 *
 * For URL source the downloaded file is cached in a transient for chunked import.
 *
 * @return array{path:string,session:string,managed:bool}|WP_Error
 */
function f2000cs_resolve_import_xml_source() {
	// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce is verified by every caller (f2000cs_handle_import_action / f2000cs_handle_analyze_action / f2000cs_handle_import_run_action).
	$source = isset( $_POST['import_source'] ) ? sanitize_text_field( wp_unslash( $_POST['import_source'] ) ) : 'file';

	if ( 'url' === $source ) {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce is verified by every caller before this runs.
		$session = isset( $_POST['import_session'] ) ? sanitize_key( wp_unslash( $_POST['import_session'] ) ) : '';

		if ( $session ) {
			$cached = get_transient( 'f2000cs_import_xml_' . $session );
			if ( is_string( $cached ) && $cached && file_exists( $cached ) ) {
				f2000cs_remember_last_import_from_request();
				return array(
					'path'    => $cached,
					'session' => $session,
					'managed' => true,
				);
			}
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce is verified by every caller before this runs.
		$url = isset( $_POST['import_xml_url'] ) ? esc_url_raw( wp_unslash( $_POST['import_xml_url'] ) ) : '';
		if ( empty( $url ) || ! preg_match( '#^https?://#i', $url ) ) {
			return new WP_Error( 'invalid_url', __( 'Вкажіть коректний URL XML файлу.', 'factorial2000-catalog-sync' ) );
		}

		$response = wp_remote_get(
			$url,
			array(
				'timeout'     => 90,  // below Cloudflare's 100 s proxy timeout
				'httpversion' => '1.1',
				'sslverify'   => f2000cs_ssl_verify_enabled(),
				'redirection' => 5,
			)
		);

		if ( is_wp_error( $response ) ) {
			return new WP_Error(
				'download_failed',
				sprintf(
					/* translators: %s: error message */
					__( 'Не вдалося завантажити XML: %s', 'factorial2000-catalog-sync' ),
					$response->get_error_message()
				)
			);
		}

		$code = (int) wp_remote_retrieve_response_code( $response );
		$body = wp_remote_retrieve_body( $response );

		if ( 200 !== $code || '' === $body ) {
			return new WP_Error(
				'download_failed',
				sprintf(
					/* translators: %d: HTTP status code */
					__( 'Не вдалося завантажити XML (HTTP %d).', 'factorial2000-catalog-sync' ),
					$code
				)
			);
		}

		$temp_file = wp_tempnam( 'f2000cs_import_' );
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- Temp file is consumed by XMLReader; WP_Filesystem cannot write to a wp_tempnam() path outside the uploads dir.
		if ( ! $temp_file || false === file_put_contents( $temp_file, $body ) ) {
			return new WP_Error( 'temp_failed', __( 'Не вдалося зберегти тимчасовий XML файл.', 'factorial2000-catalog-sync' ) );
		}

		$session = $session ? $session : wp_generate_password( 16, false, false );
		set_transient( 'f2000cs_import_xml_' . $session, $temp_file, HOUR_IN_SECONDS );

		f2000cs_remember_last_import_from_request();

		return array(
			'path'    => $temp_file,
			'session' => $session,
			'managed' => true,
		);
	}

	// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce is verified by every caller before this runs.
	if ( isset( $_FILES['import_xml_file']['error'], $_FILES['import_xml_file']['tmp_name'] ) && UPLOAD_ERR_OK === (int) $_FILES['import_xml_file']['error'] ) {
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.MissingUnslash, WordPress.Security.NonceVerification.Missing -- Server-generated upload path; unslashing would corrupt Windows paths; nonce is verified by every caller.
		$file_path = sanitize_text_field( $_FILES['import_xml_file']['tmp_name'] );

		f2000cs_remember_last_import_from_request();

		return array(
			'path'    => $file_path,
			'session' => '',
			'managed' => false,
		);
	}

	return new WP_Error( 'no_file', __( 'Помилка завантаження файлу. Виберіть XML з комп’ютера або вкажіть URL.', 'factorial2000-catalog-sync' ) );
}

/**
 * Delete cached import XML temp file and its transient.
 *
 * @param string $session Import session key.
 * @return void
 */
function f2000cs_cleanup_import_xml_session( $session ) {
	$session = sanitize_key( $session );
	if ( '' === $session ) {
		return;
	}

	$cached = get_transient( 'f2000cs_import_xml_' . $session );
	delete_transient( 'f2000cs_import_xml_' . $session );
	delete_transient( 'f2000cs_import_resume_' . $session );

	if ( is_string( $cached ) && $cached && file_exists( $cached ) ) {
		wp_delete_file( $cached );
	}
}

/**
 * Save import checkpoint so the user can resume after a page reload.
 *
 * @param string $session  Import session key.
 * @param int    $offset   Cumulative offset of the last successfully imported product.
 * @param array  $context  Import settings (sku_prefix, new_category, import_variations, selected_attributes).
 * @return void
 */
function f2000cs_import_save_checkpoint( string $session, int $offset, array $context ): void {
	if ( '' === $session ) {
		return;
	}

	set_transient(
		'f2000cs_import_resume_' . $session,
		array(
			'offset'              => $offset,
			'total'               => (int) ( $context['total'] ?? 0 ),
			'source'              => (string) ( $context['source'] ?? '' ),
			'sku_prefix'          => (string) ( $context['sku_prefix'] ?? '' ),
			'new_category'        => (bool) ( $context['new_category'] ?? false ),
			'import_variations'   => (bool) ( $context['import_variations'] ?? false ),
			'selected_attributes' => (array) ( $context['selected_attributes'] ?? array() ),
		),
		HOUR_IN_SECONDS
	);
}

/**
 * Find a pending import session that can be resumed.
 *
 * Scans all f2000cs_import_resume_* transients and returns the first one
 * whose source XML file still exists on disk.
 *
 * @return array{session:string, offset:int, context:array}|null
 */
function f2000cs_get_pending_import(): ?array {
	global $wpdb;

	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- One-off admin scan, not performance-sensitive.
	$keys = $wpdb->get_col(
		$wpdb->prepare(
			"SELECT option_name FROM {$wpdb->options}
			WHERE option_name LIKE %s
			AND option_name NOT LIKE %s
			LIMIT 5",
			'_transient_f2000cs_import_resume_%',
			'_transient_timeout%'
		)
	);

	foreach ( $keys as $key ) {
		$session = str_replace( '_transient_f2000cs_import_resume_', '', $key );
		$session = sanitize_key( $session );

		if ( '' === $session ) {
			continue;
		}

		$temp_file = get_transient( 'f2000cs_import_xml_' . $session );

		if ( ! is_string( $temp_file ) || ! $temp_file || ! file_exists( $temp_file ) ) {
			delete_transient( 'f2000cs_import_resume_' . $session );
			continue;
		}

		$checkpoint = get_transient( 'f2000cs_import_resume_' . $session );

		if ( ! is_array( $checkpoint ) ) {
			continue;
		}

		return array(
			'session' => $session,
			'offset'  => (int) ( $checkpoint['offset'] ?? 0 ),
			'context' => $checkpoint,
		);
	}

	return null;
}

/**
 * Render a resume notice if there is a pending import session.
 *
 * @return void
 */
function f2000cs_render_import_resume_notice(): void {
	$pending = f2000cs_get_pending_import();

	if ( ! $pending ) {
		return;
	}

	$prefix = $pending['context']['sku_prefix'] ?? '';
	$mode   = empty( $pending['context']['import_variations'] ) ? 'simple' : 'variable';
	$total  = (int) ( $pending['context']['total'] ?? 0 );
	$offset = (int) $pending['offset'];
	$source = (string) ( $pending['context']['source'] ?? '' );
	?>
	<div class="notice notice-info f2000cs-import-resume"
		data-f2000cs-resume="<?php echo esc_attr( wp_json_encode( $pending ) ); ?>"
		data-f2000cs-nonce="<?php echo esc_attr( wp_create_nonce( 'f2000cs_import_action' ) ); ?>">
		<p>
			<strong><?php esc_html_e( 'Незавершений імпорт', 'factorial2000-catalog-sync' ); ?></strong>
			<?php if ( $total > 0 ) : ?>
				—
				<?php
				printf(
					/* translators: 1: current offset, 2: total products */
					esc_html__( 'зупинився на %1$d / %2$d товарів.', 'factorial2000-catalog-sync' ),
					(int) $offset,
					(int) $total
				);
				?>
			<?php endif; ?>
			<?php if ( '' !== $source ) : ?>
				<br><small><?php echo esc_html( $source ); ?></small>
			<?php endif; ?>
		</p>
		<button type="button" class="button button-primary f2000cs-import-resume__btn">
			<?php esc_html_e( 'Продовжити імпорт', 'factorial2000-catalog-sync' ); ?>
		</button>
		<button type="button" class="button f2000cs-import-resume__discard">
			<?php esc_html_e( 'Скасувати', 'factorial2000-catalog-sync' ); ?>
		</button>
	</div>
	<?php
}

function f2000cs_handle_import_action() {
	if ( ! isset( $_POST['f2000cs_import_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['f2000cs_import_nonce'] ) ), 'f2000cs_import_action' ) ) {
		wp_send_json_error( array( 'message' => __( 'Помилка перевірки безпеки (nonce). Оновіть сторінку.', 'factorial2000-catalog-sync' ) ) );
	}

	if ( ! current_user_can( 'manage_options' ) ) {
		wp_send_json_error( array( 'message' => __( 'Недостатньо прав.', 'factorial2000-catalog-sync' ) ) );
	}

	$resolved = f2000cs_resolve_import_xml_source();
	if ( is_wp_error( $resolved ) ) {
		wp_send_json_error( array( 'message' => $resolved->get_error_message() ) );
	}

	$file_path         = $resolved['path'];
	$offset            = isset( $_POST['offset'] ) ? intval( $_POST['offset'] ) : 0;
	$new_category      = isset( $_POST['new_category'] ) && '1' === $_POST['new_category'];
	$import_variations = isset( $_POST['import_variations'] ) && '1' === $_POST['import_variations'];
	$sku_prefix        = isset( $_POST['sku_prefix'] ) ? sanitize_text_field( wp_unslash( $_POST['sku_prefix'] ) ) : '';

	$selected_attributes = array();
	if ( isset( $_POST['selected_attributes'] ) ) {
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- JSON payload is decoded then sanitized via map_deep() below.
		$decoded = json_decode( wp_unslash( $_POST['selected_attributes'] ), true );
		if ( is_array( $decoded ) ) {
			$selected_attributes = map_deep( $decoded, 'sanitize_text_field' );
		}
	}

	set_transient( 'f2000cs_import_variations_temp', $import_variations ? '1' : '0', HOUR_IN_SECONDS );
	set_transient( 'f2000cs_selected_attributes_temp', $selected_attributes, HOUR_IN_SECONDS );

	$xml_parser = new \F2000CS\XML_Parser( $file_path, $new_category, $sku_prefix );
	try {
		$result = $xml_parser->import_products( $offset, 1 );

		// Progress/resume offset advances by processed items (success + skip), not only imports.
		$processed  = isset( $result['processed'] ) ? (int) $result['processed'] : ( (int) $result['imported'] + (int) ( $result['skipped'] ?? 0 ) );
		$cumulative = $offset + $processed;

		if ( ! empty( $result['finished'] ) ) {
			delete_transient( 'f2000cs_import_variations_temp' );
			delete_transient( 'f2000cs_selected_attributes_temp' );
			if ( ! empty( $resolved['session'] ) ) {
				f2000cs_cleanup_import_xml_session( $resolved['session'] );
			}
		} elseif ( ! empty( $resolved['session'] ) ) {
			// Save checkpoint so the user can resume after a page reload.
			f2000cs_import_save_checkpoint(
				$resolved['session'],
				$cumulative,
				array(
					'total'               => $result['total'],
					'source'              => 'url' === ( isset( $_POST['import_source'] ) ? sanitize_text_field( wp_unslash( $_POST['import_source'] ) ) : '' ) // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Read-only, nonce verified above.
						? ( isset( $_POST['import_xml_url'] ) ? esc_url_raw( wp_unslash( $_POST['import_xml_url'] ) ) : '' ) // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Read-only.
						: '',
					'sku_prefix'          => $sku_prefix,
					'new_category'        => $new_category,
					'import_variations'   => $import_variations,
					'selected_attributes' => $selected_attributes,
				)
			);
		}

		wp_send_json_success(
			array(
				'imported'       => $cumulative,
				'total'          => $result['total'],
				'finished'       => $result['finished'],
				'import_session' => $resolved['session'],
			)
		);
	} catch ( Exception $e ) {
		if ( ! empty( $resolved['session'] ) ) {
			f2000cs_cleanup_import_xml_session( $resolved['session'] );
		}
		wp_send_json_error( array( 'message' => $e->getMessage() ) );
	}
}
add_action( 'wp_ajax_f2000cs_import_action', 'f2000cs_handle_import_action' );
add_action( 'wp_ajax_f2000cs_import_discard', 'f2000cs_handle_import_discard' );
add_action( 'wp_ajax_f2000cs_analyze_groups', 'f2000cs_handle_analyze_groups' );
add_action( 'wp_ajax_f2000cs_update_fields_action', 'f2000cs_handle_update_fields_action' );

/**
 * AJAX: discard a pending import session (remove temp files + transients).
 *
 * @return void
 */
function f2000cs_handle_import_discard() {
	check_ajax_referer( 'f2000cs_import_action', 'f2000cs_import_nonce' );

	if ( ! current_user_can( 'manage_options' ) ) {
		wp_send_json_error( array( 'message' => __( 'Недостатньо прав.', 'factorial2000-catalog-sync' ) ) );
	}

	// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Verified by check_ajax_referer() above.
	$session = isset( $_POST['import_session'] ) ? sanitize_key( wp_unslash( $_POST['import_session'] ) ) : '';

	if ( '' !== $session ) {
		f2000cs_cleanup_import_xml_session( $session );
	}

	wp_send_json_success();
}

/**
 * Handle the "Оновити окремі поля" AJAX action: for existing products matched
 * by SKU (with prefix), update only the fields the user selected, one XML
 * offer per request (mirrors the chunked import flow).
 *
 * @return void
 */
function f2000cs_handle_update_fields_action() {
	if ( ! isset( $_POST['f2000cs_import_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['f2000cs_import_nonce'] ) ), 'f2000cs_import_action' ) ) {
		wp_send_json_error( array( 'message' => __( 'Помилка перевірки безпеки (nonce). Оновіть сторінку.', 'factorial2000-catalog-sync' ) ) );
	}

	if ( ! current_user_can( 'manage_options' ) ) {
		wp_send_json_error( array( 'message' => __( 'Недостатньо прав.', 'factorial2000-catalog-sync' ) ) );
	}

	if ( function_exists( 'f2000cs_is_pro' ) && ! f2000cs_is_pro() ) {
		wp_send_json_error( array( 'message' => __( 'Оновлення окремих полів доступне лише у Pro версії.', 'factorial2000-catalog-sync' ) ) );
	}

	$resolved = f2000cs_resolve_import_xml_source();
	if ( is_wp_error( $resolved ) ) {
		wp_send_json_error( array( 'message' => $resolved->get_error_message() ) );
	}

	$file_path  = $resolved['path'];
	$offset     = isset( $_POST['offset'] ) ? intval( $_POST['offset'] ) : 0;
	$sku_prefix = isset( $_POST['sku_prefix'] ) ? sanitize_text_field( wp_unslash( $_POST['sku_prefix'] ) ) : '';

	$fields = array();
	if ( isset( $_POST['update_fields'] ) && is_array( $_POST['update_fields'] ) ) {
		$fields = array_map( 'sanitize_text_field', wp_unslash( $_POST['update_fields'] ) );
	}

	$fields = array_values( array_intersect( \F2000CS\Fields_Updater::ALLOWED_FIELDS, $fields ) );

	if ( empty( $fields ) ) {
		if ( ! empty( $resolved['session'] ) ) {
			f2000cs_cleanup_import_xml_session( $resolved['session'] );
		}
		wp_send_json_error( array( 'message' => __( 'Виберіть хоча б одне поле для оновлення.', 'factorial2000-catalog-sync' ) ) );
	}

	$updater = new \F2000CS\Fields_Updater( $file_path, $sku_prefix, $fields );

	try {
		$result = $updater->update_fields( $offset, 1 );

		if ( ! empty( $result['finished'] ) && ! empty( $resolved['session'] ) ) {
			f2000cs_cleanup_import_xml_session( $resolved['session'] );
		}

		wp_send_json_success(
			array(
				'imported'       => $result['processed'] + $offset,
				'total'          => $result['total'],
				'finished'       => $result['finished'],
				'updated'        => $result['updated'],
				'not_found'      => $result['not_found'],
				'skipped'        => $result['skipped'],
				'import_session' => $resolved['session'],
			)
		);
	} catch ( Exception $e ) {
		if ( ! empty( $resolved['session'] ) ) {
			f2000cs_cleanup_import_xml_session( $resolved['session'] );
		}
		wp_send_json_error( array( 'message' => $e->getMessage() ) );
	}
}

/**
 * Handle analyze groups action - scans XML and returns variable product groups
 */
function f2000cs_handle_analyze_groups() {
	if ( ! isset( $_POST['f2000cs_import_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['f2000cs_import_nonce'] ) ), 'f2000cs_import_action' ) ) {
		wp_send_json_error( array( 'message' => __( 'Помилка перевірки безпеки (nonce). Оновіть сторінку.', 'factorial2000-catalog-sync' ) ) );
	}

	if ( ! current_user_can( 'manage_options' ) ) {
		wp_send_json_error( array( 'message' => __( 'Недостатньо прав.', 'factorial2000-catalog-sync' ) ) );
	}

	$resolved = f2000cs_resolve_import_xml_source();
	if ( is_wp_error( $resolved ) ) {
		wp_send_json_error( array( 'message' => $resolved->get_error_message() ) );
	}

	try {
		$groups = f2000cs_analyze_variable_groups( $resolved['path'] );
		if ( ! empty( $resolved['session'] ) ) {
			// Keep URL cache for the following import with selected attributes.
			wp_send_json_success(
				array(
					'groups'         => $groups,
					'import_session' => $resolved['session'],
				)
			);
			return;
		}

		wp_send_json_success( array( 'groups' => $groups ) );
	} catch ( Exception $e ) {
		if ( ! empty( $resolved['session'] ) ) {
			f2000cs_cleanup_import_xml_session( $resolved['session'] );
		}
		wp_send_json_error( array( 'message' => $e->getMessage() ) );
	}
}

/**
 * Analyze XML file and extract variable product groups with their attributes
 *
 * @param string $file_path Path to XML file.
 * @return array Groups data.
 */
function f2000cs_analyze_variable_groups( string $file_path ): array {
	$reader = new XMLReader();

	// phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- XMLReader emits warnings on broken/missing files; the return value is checked and an Exception is thrown instead.
	if ( ! @$reader->open( $file_path, null, LIBXML_NONET ) ) {
		throw new Exception( 'Failed to open XML file.' );
	}

	$groups = array();

	while ( $reader->read() ) {
		if ( $reader->nodeType !== XMLReader::ELEMENT || $reader->name !== 'offer' ) {
			continue;
		}

		$offer = simplexml_load_string( $reader->readOuterXML(), null, LIBXML_NONET );

		// Only offers with a real group_id (empty( '0' ) is true in PHP — do not use empty()).
		$group_id = isset( $offer['group_id'] ) ? trim( (string) $offer['group_id'] ) : '';
		if ( '' === $group_id ) {
			continue;
		}

		$name = (string) $offer->name;
		if ( ! empty( $offer->name_ua ) ) {
			$name = (string) $offer->name_ua;
		}

		$offer_data = array(
			'id'         => (string) $offer['id'],
			'name'       => $name,
			'image'      => isset( $offer->picture[0] ) ? (string) $offer->picture[0] : '',
			'attributes' => array(),
		);

		if ( isset( $offer->param ) ) {
			foreach ( $offer->param as $param ) {
				$attr_name  = (string) $param['name'];
				$attr_value = trim( (string) $param );
				if ( '' === $attr_name || '' === $attr_value ) {
					continue;
				}

				$unit       = isset( $param['unit'] ) ? ' ' . trim( (string) $param['unit'] ) : '';
				$full_value = $attr_value . $unit;

				// Match import: multiple params with the same name are joined.
				if ( isset( $offer_data['attributes'][ $attr_name ] ) ) {
					$offer_data['attributes'][ $attr_name ] .= '; ' . $full_value;
				} else {
					$offer_data['attributes'][ $attr_name ] = $full_value;
				}
			}
		}

		// Match import: vendor becomes attribute «Виробник» when missing.
		$vendor = isset( $offer->vendor ) ? trim( (string) $offer->vendor ) : '';
		if ( '' !== $vendor && ! isset( $offer_data['attributes']['Виробник'] ) ) {
			$offer_data['attributes']['Виробник'] = $vendor;
		}

		if ( ! isset( $groups[ $group_id ] ) ) {
			$groups[ $group_id ] = array(
				'name'             => $offer_data['name'],
				'image'            => $offer_data['image'],
				'variations_count' => 0,
				'variations'       => array(),
				'all_attributes'   => array(),
			);
		}

		$groups[ $group_id ]['variations'][] = $offer_data;
		++$groups[ $group_id ]['variations_count'];

		foreach ( $offer_data['attributes'] as $attr_name => $attr_value ) {
			if ( ! isset( $groups[ $group_id ]['all_attributes'][ $attr_name ] ) ) {
				$groups[ $group_id ]['all_attributes'][ $attr_name ] = array();
			}
			if ( ! in_array( $attr_value, $groups[ $group_id ]['all_attributes'][ $attr_name ], true ) ) {
				$groups[ $group_id ]['all_attributes'][ $attr_name ][] = $attr_value;
			}
		}
	}

	$reader->close();

	foreach ( $groups as $group_id => &$group ) {
		$varying_attributes = array();

		foreach ( $group['all_attributes'] as $attr_name => $attr_values ) {
			// Показуємо ВСІ атрибути, а не тільки ті що варіюються
			// Атрибут варіюється якщо має більше 1 значення
			$is_varying = count( $attr_values ) > 1;

			$varying_attributes[] = array(
				'name'       => $attr_name,
				'values'     => $attr_values,
				'is_varying' => $is_varying,
			);
		}

		$group['attributes'] = $varying_attributes;
		unset( $group['all_attributes'] ); // Видаляємо тимчасові дані
		unset( $group['variations'] ); // Не передаємо всі варіації на фронтенд
	}

	// Фільтруємо групи - показуємо тільки ті що мають 2+ варіації
	$filtered_groups = array();
	foreach ( $groups as $group_id => $group ) {
		if ( $group['variations_count'] >= 2 ) {
			$filtered_groups[ $group_id ] = $group;
		}
	}

	return $filtered_groups;
}
