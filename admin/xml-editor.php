<?php
/**
 * XML Editor module: admin card + AJAX handlers.
 *
 * The editor filters one large supplier export into a separate export by
 * categories and/or individual offers. The source XML is cached per session
 * under uploads/f2000cs-editor-sessions/ and the generated file is written
 * to uploads/f2000cs-exports/.
 *
 * Structure:
 * 1. Session storage        — per-session source files under uploads/.
 * 2. Source resolution      — feed URL, uploaded file or local path → XML string.
 * 3. Request helpers        — sanitized access to the AJAX payload.
 * 4. AJAX handlers          — load / offers / offer_ids / generate.
 * 5. Admin UI               — the 3-column editor card.
 *
 * @package Factorial2000_Catalog_Sync
 */

defined( 'ABSPATH' ) || exit;

// =========================================================================
// 1. Session storage
// =========================================================================

/**
 * Session storage directory for the XML editor source files.
 *
 * @return string Directory path or empty string on failure.
 */
function f2000cs_xml_editor_session_dir(): string {
	if ( function_exists( 'f2000cs_ensure_editor_sessions_dir' ) ) {
		$dir = f2000cs_ensure_editor_sessions_dir();
		return is_dir( $dir ) ? $dir : '';
	}

	$upload_dir = wp_upload_dir();
	$dir        = $upload_dir['basedir'] . '/f2000cs-editor-sessions';

	if ( ! wp_mkdir_p( $dir ) ) {
		return '';
	}

	return $dir;
}

/**
 * Sanitize a session token (used in file names only).
 *
 * @param string $token Raw token.
 * @return string
 */
function f2000cs_xml_editor_sanitize_token( string $token ): string {
	$token = sanitize_key( $token );

	return (string) preg_replace( '/[^a-z0-9_-]/', '', $token );
}

/**
 * Resolve a session token to an existing source file path.
 *
 * @param string $token Session token.
 * @return string File path or empty string.
 */
function f2000cs_xml_editor_session_path( string $token ): string {
	$dir   = f2000cs_xml_editor_session_dir();
	$token = f2000cs_xml_editor_sanitize_token( $token );

	if ( '' === $dir || '' === $token ) {
		return '';
	}

	$path = $dir . '/' . $token . '.xml';

	return is_file( $path ) ? $path : '';
}

/**
 * Remove session files older than 24 hours.
 *
 * @return void
 */
function f2000cs_xml_editor_prune_sessions(): void {
	$dir = f2000cs_xml_editor_session_dir();

	if ( '' === $dir ) {
		return;
	}

	$now = time();

	foreach ( scandir( $dir ) as $entry ) {
		if ( 'xml' !== strtolower( pathinfo( $entry, PATHINFO_EXTENSION ) ) ) {
			continue;
		}

		$file = $dir . '/' . $entry;

		if ( is_file( $file ) && $now - (int) filemtime( $file ) > DAY_IN_SECONDS ) {
			wp_delete_file( $file );
		}
	}
}

// =========================================================================
// 2. Source resolution
// =========================================================================

/**
 * Read the XML source contents.
 *
 * Supports three source kinds:
 * - uploaded file (multipart input),
 * - http(s) feed URL,
 * - local file path (e.g. an export dropped into a shared folder).
 *
 * @param string $source_type 'url' or 'file'.
 * @param string $source_url  Feed URL or local path (used for 'url').
 * @param array  $file        $_FILES entry (used for 'file').
 * @return array{content: string, label: string, error: string}
 */
function f2000cs_xml_editor_resolve_source( string $source_type, string $source_url, array $file ): array {
	$result = array(
		'content' => '',
		'label'   => '',
		'error'   => '',
	);

	if ( 'file' === $source_type ) {
		return f2000cs_xml_editor_read_uploaded_file( $file, $result );
	}

	return f2000cs_xml_editor_fetch_from_url( trim( $source_url ), $result );
}

/**
 * Read the XML contents of an uploaded file.
 *
 * @param array $file    $_FILES entry.
 * @param array $result  Result template.
 * @return array
 */
function f2000cs_xml_editor_read_uploaded_file( array $file, array $result ): array {
	if ( empty( $file ) || UPLOAD_ERR_OK !== (int) ( $file['error'] ?? -1 ) ) {
		return array_merge( $result, array( 'error' => __( 'Оберіть XML файл для завантаження.', 'factorial2000-catalog-sync' ) ) );
	}

	$name = isset( $file['name'] ) ? sanitize_file_name( wp_unslash( $file['name'] ) ) : '';

	if ( 'xml' !== strtolower( pathinfo( $name, PATHINFO_EXTENSION ) ) ) {
		return array_merge( $result, array( 'error' => __( 'Файл повинен мати розширення .xml.', 'factorial2000-catalog-sync' ) ) );
	}

	// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.MissingUnslash, WordPress.Security.NonceVerification.Missing -- Server-generated upload path (unslashing would corrupt Windows paths); nonce verified in the dispatcher.
	$tmp_path = sanitize_text_field( (string) ( $file['tmp_name'] ?? '' ) );

	// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Uploaded temp file consumed by SimpleXML; WP_Filesystem is not applicable to php-upload temp files.
	$content = (string) file_get_contents( $tmp_path );

	return array_merge(
		$result,
		array(
			'content' => $content,
			'label'   => $name,
		)
	);
}

/**
 * Fetch the XML contents from a feed URL or a local file path.
 *
 * @param string $source_url Feed URL or existing local path.
 * @param array  $result     Result template.
 * @return array
 */
function f2000cs_xml_editor_fetch_from_url( string $source_url, array $result ): array {
	if ( '' === $source_url ) {
		return array_merge( $result, array( 'error' => __( 'Вкажіть URL XML файлу.', 'factorial2000-catalog-sync' ) ) );
	}

	// Local file path support (e.g. a feed exported into a shared folder).
	if ( is_file( $source_url ) ) {
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Local source file consumed by SimpleXML; WP_Filesystem is not applicable here.
		$content = (string) file_get_contents( $source_url );

		return array_merge(
			$result,
			array(
				'content' => $content,
				'label'   => basename( $source_url ),
			)
		);
	}

	if ( ! preg_match( '#^https?://#i', $source_url ) ) {
		return array_merge( $result, array( 'error' => __( 'Вкажіть коректний URL XML файлу (http/https) або шлях до локального файлу.', 'factorial2000-catalog-sync' ) ) );
	}

	$response = wp_remote_get(
		$source_url,
		array(
			'timeout'     => 90,  // below Cloudflare's 100 s proxy timeout
			'redirection' => 5,
			'sslverify'   => f2000cs_ssl_verify_enabled(),
		)
	);

	if ( is_wp_error( $response ) ) {
		return array_merge(
			$result,
			array(
				/* translators: %s: low-level HTTP error message from the request. */
				'error' => sprintf( __( 'Не вдалося завантажити XML: %s', 'factorial2000-catalog-sync' ), $response->get_error_message() ),
			)
		);
	}

	$status = (int) wp_remote_retrieve_response_code( $response );

	if ( 200 !== $status ) {
		return array_merge(
			$result,
			array(
				/* translators: %d: HTTP status code returned by the XML server. */
				'error' => sprintf( __( 'XML-сервер повернув HTTP %d. Перевірте, що посилання відкривається у браузері.', 'factorial2000-catalog-sync' ), $status ),
			)
		);
	}

	$content = wp_remote_retrieve_body( $response );

	if ( '' === trim( $content ) ) {
		return array_merge( $result, array( 'error' => __( 'XML файл порожній.', 'factorial2000-catalog-sync' ) ) );
	}

	return array_merge(
		$result,
		array(
			'content' => $content,
			'label'   => $source_url,
		)
	);
}

// =========================================================================
// 3. Request helpers
// =========================================================================

/**
 * Read a string array field from the current AJAX request.
 *
 * @param string $key POST field name.
 * @return array<string>
 */
function f2000cs_xml_editor_string_array_from_post( string $key ): array {
	// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce is verified in f2000cs_xml_editor_ajax() before dispatch.
	if ( ! isset( $_POST[ $key ] ) || ! is_array( $_POST[ $key ] ) ) {
		return array();
	}

	// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce is verified in f2000cs_xml_editor_ajax() before dispatch.
	return array_map( 'sanitize_text_field', wp_unslash( $_POST[ $key ] ) );
}

/**
 * Filter conditions extracted from the current AJAX request.
 *
 * @return array
 */
function f2000cs_xml_editor_conditions_from_post(): array {
	// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce is verified in f2000cs_xml_editor_ajax() before dispatch.
	$only_in_stock = isset( $_POST['only_in_stock'] ) && '1' === sanitize_text_field( wp_unslash( $_POST['only_in_stock'] ) );

	// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce is verified in f2000cs_xml_editor_ajax() before dispatch.
	$min_price = isset( $_POST['min_price'] ) ? (float) sanitize_text_field( wp_unslash( $_POST['min_price'] ) ) : 0.0;

	// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce is verified in f2000cs_xml_editor_ajax() before dispatch.
	$max_price = isset( $_POST['max_price'] ) ? (float) sanitize_text_field( wp_unslash( $_POST['max_price'] ) ) : 0.0;

	return array(
		'only_in_stock' => $only_in_stock,
		'min_price'     => $min_price,
		'max_price'     => $max_price,
	);
}

/**
 * Session token from the current AJAX request, resolved to a source file.
 *
 * @return string Source file path or empty string.
 */
function f2000cs_xml_editor_session_from_post(): string {
	// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce is verified in f2000cs_xml_editor_ajax() before dispatch.
	$token = isset( $_POST['token'] ) ? sanitize_text_field( wp_unslash( $_POST['token'] ) ) : '';

	return f2000cs_xml_editor_session_path( $token );
}

/**
 * Build a ready-to-use XML_Editor instance for a session source file.
 *
 * @param string $source_path Source file path.
 * @return \F2000CS\XML_Editor|null Editor instance or null on failure.
 */
function f2000cs_xml_editor_make_editor( string $source_path ): ?\F2000CS\XML_Editor {
	$editor = new \F2000CS\XML_Editor( $source_path );

	return $editor->load() ? $editor : null;
}

// =========================================================================
// 4. AJAX handlers
// =========================================================================

/**
 * AJAX: load a source XML (URL, upload or local path) and parse it.
 *
 * @return void
 */
function f2000cs_xml_editor_ajax_load() {
	// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce is verified in f2000cs_xml_editor_ajax() before dispatch.
	$source_type = isset( $_POST['source_type'] ) ? sanitize_key( wp_unslash( $_POST['source_type'] ) ) : 'url';
	// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce is verified in f2000cs_xml_editor_ajax() before dispatch.
	$source_url = isset( $_POST['source_url'] ) ? esc_url_raw( wp_unslash( $_POST['source_url'] ) ) : '';
	// phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Nonce verified in the dispatcher; the file entry is sanitized inside f2000cs_xml_editor_read_uploaded_file().
	$file = isset( $_FILES['xml_file'] ) ? $_FILES['xml_file'] : array();

	$resolved = f2000cs_xml_editor_resolve_source( $source_type, $source_url, $file );

	if ( '' !== $resolved['error'] ) {
		wp_send_json_error( array( 'message' => $resolved['error'] ) );
	}

	$dir = f2000cs_xml_editor_session_dir();

	if ( '' === $dir ) {
		wp_send_json_error( array( 'message' => __( 'Не вдалося створити тимчасову папку для XML.', 'factorial2000-catalog-sync' ) ) );
	}

	f2000cs_xml_editor_prune_sessions();

	// Must be lowercase a-z0-9: f2000cs_xml_editor_sanitize_token() runs every
	// subsequent request's token through sanitize_key(), which lowercases and
	// strips other characters. wp_generate_password() includes uppercase
	// letters, so an unmodified token would build a session path that never
	// matches the saved file on case-sensitive (Linux) filesystems.
	$token     = strtolower( wp_generate_password( 20, false, false ) );
	$file_path = $dir . '/' . $token . '.xml';

	// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- Session temp file consumed by SimpleXML; WP_Filesystem is not applicable here.
	if ( false === file_put_contents( $file_path, $resolved['content'] ) ) {
		wp_send_json_error( array( 'message' => __( 'Не вдалося зберегти тимчасовий XML файл.', 'factorial2000-catalog-sync' ) ) );
	}

	$editor = f2000cs_xml_editor_make_editor( $file_path );

	if ( ! $editor ) {
		wp_delete_file( $file_path );
		wp_send_json_error( array( 'message' => __( 'Не вдалося розпарсити XML файл. Перевірте, що це коректний YML/XML вигрузки.', 'factorial2000-catalog-sync' ) ) );
	}

	$file_size = is_file( $file_path ) ? filesize( $file_path ) : 0;
	$response  = array(
		'token'        => $token,
		'categories'   => $editor->get_categories(),
		'total_offers' => $editor->get_total_offers(),
		'source'       => $resolved['label'],
	);

	// Files > 50 MB are slow to generate; warn the user once.
	if ( $file_size > 50 * 1024 * 1024 ) {
		$response['warning'] = sprintf(
			/* translators: %.0f: file size in megabytes */
			__( 'Файл %.0f MB — великий. Перегляд працює миттєво, але генерація вигрузки може тривати до хвилини.', 'factorial2000-catalog-sync' ),
			$file_size / 1048576
		);
	}

	wp_send_json_success( $response );
}

/**
 * AJAX: paginated offers list for the selected categories.
 *
 * @return void
 */
function f2000cs_xml_editor_ajax_offers() {
	$source_path = f2000cs_xml_editor_session_from_post();

	if ( '' === $source_path ) {
		wp_send_json_error( array( 'message' => __( 'Сесію не знайдено. Завантажте XML ще раз.', 'factorial2000-catalog-sync' ) ) );
	}

	$category_ids = f2000cs_xml_editor_string_array_from_post( 'category_ids' );
	// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce is verified in f2000cs_xml_editor_ajax() before dispatch.
	$page = isset( $_POST['page'] ) ? max( 1, absint( $_POST['page'] ) ) : 1;
	// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce is verified in f2000cs_xml_editor_ajax() before dispatch.
	$search = isset( $_POST['search'] ) ? sanitize_text_field( wp_unslash( $_POST['search'] ) ) : '';

	$editor = f2000cs_xml_editor_make_editor( $source_path );

	if ( ! $editor ) {
		wp_send_json_error( array( 'message' => __( 'Не вдалося розпарсити XML.', 'factorial2000-catalog-sync' ) ) );
	}

	$conditions           = f2000cs_xml_editor_conditions_from_post();
	$conditions['search'] = $search;
	$limit                = 200;
	$offset               = ( $page - 1 ) * $limit;

	$rows  = $editor->get_offers( $category_ids, $limit, $offset, $conditions );
	$total = $editor->count_offers( $category_ids, $conditions );

	wp_send_json_success(
		array(
			'offers'   => $rows,
			'total'    => $total,
			'page'     => $page,
			'has_more' => ( $offset + count( $rows ) ) < $total,
		)
	);
}

/**
 * AJAX: all offer ids matching the selected categories and conditions.
 *
 * Used by the products "select all" toggle to deselect the whole set.
 *
 * @return void
 */
function f2000cs_xml_editor_ajax_offer_ids() {
	$source_path = f2000cs_xml_editor_session_from_post();

	if ( '' === $source_path ) {
		wp_send_json_error( array( 'message' => __( 'Сесію не знайдено. Завантажте XML ще раз.', 'factorial2000-catalog-sync' ) ) );
	}

	$editor = f2000cs_xml_editor_make_editor( $source_path );

	if ( ! $editor ) {
		wp_send_json_error( array( 'message' => __( 'Не вдалося розпарсити XML.', 'factorial2000-catalog-sync' ) ) );
	}

	$ids = $editor->get_offer_ids( f2000cs_xml_editor_string_array_from_post( 'category_ids' ), f2000cs_xml_editor_conditions_from_post() );

	wp_send_json_success( array( 'offer_ids' => $ids ) );
}

/**
 * AJAX: generate the filtered XML and save it to the exports folder.
 *
 * @return void
 */
function f2000cs_xml_editor_ajax_generate() {
	$source_path = f2000cs_xml_editor_session_from_post();

	if ( '' === $source_path ) {
		wp_send_json_error( array( 'message' => __( 'Сесію не знайдено. Завантажте XML ще раз.', 'factorial2000-catalog-sync' ) ) );
	}

	$category_ids = f2000cs_xml_editor_string_array_from_post( 'category_ids' );
	$extra_ids    = f2000cs_xml_editor_string_array_from_post( 'extra_offer_ids' );
	$excluded_ids = f2000cs_xml_editor_string_array_from_post( 'excluded_ids' );

	if ( empty( $category_ids ) && empty( $extra_ids ) ) {
		wp_send_json_error( array( 'message' => __( 'Оберіть хоча б одну категорію або товар.', 'factorial2000-catalog-sync' ) ) );
	}

	$editor = f2000cs_xml_editor_make_editor( $source_path );

	if ( ! $editor ) {
		wp_send_json_error( array( 'message' => __( 'Не вдалося розпарсити XML.', 'factorial2000-catalog-sync' ) ) );
	}

	$options = f2000cs_xml_editor_conditions_from_post();
	// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce is verified in f2000cs_xml_editor_ajax() before dispatch.
	$options['keep_oldprice'] = isset( $_POST['keep_oldprice'] ) && '1' === sanitize_text_field( wp_unslash( $_POST['keep_oldprice'] ) );
	// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce is verified in f2000cs_xml_editor_ajax() before dispatch.
	$options['sku_prefix'] = isset( $_POST['sku_prefix'] ) ? sanitize_text_field( wp_unslash( $_POST['sku_prefix'] ) ) : '';

	// Let the server finish even if the proxy/browser drops the connection
	// mid-generation (Cloudflare kills idle AJAX at ~100 s).
	if ( function_exists( 'ignore_user_abort' ) ) {
		ignore_user_abort( true );
	}
	if ( function_exists( 'set_time_limit' ) ) {
		// phpcs:ignore Squiz.PHP.DiscouragedFunctions.Discouraged, WordPress.PHP.NoSilencedErrors.Discouraged -- One-time generation, user-initiated; @ suppresses host-imposed limit warnings.
		@set_time_limit( 0 );
	}

	$result = $editor->generate( $category_ids, $extra_ids, $excluded_ids, $options );

	if ( empty( $result['xml'] ) ) {
		wp_send_json_error( array( 'message' => __( 'Жоден товар не відповідає вибраним умовам.', 'factorial2000-catalog-sync' ) ) );
	}

	$saved = $editor->save( $result['xml'] );

	if ( ! $saved['success'] ) {
		wp_send_json_error( array( 'message' => __( 'Не вдалося зберегти відфільтрований XML файл.', 'factorial2000-catalog-sync' ) ) );
	}

	f2000cs_log(
		sprintf(
			'XML editor: generated %d offers, file %s',
			$result['count'],
			basename( $saved['path'] )
		)
	);

	wp_send_json_success(
		array(
			'download_url' => $saved['url'],
			'count'        => $result['count'],
			'file_name'    => basename( $saved['path'] ),
		)
	);
}

/**
 * AJAX dispatcher for the XML editor.
 *
 * @return void
 */
function f2000cs_xml_editor_ajax() {
	check_ajax_referer( 'f2000cs_xml_editor', 'nonce' );

	if ( ! current_user_can( 'manage_options' ) ) {
		wp_send_json_error( array( 'message' => __( 'Недостатньо прав для цієї дії.', 'factorial2000-catalog-sync' ) ) );
	}

	if ( function_exists( 'f2000cs_is_pro' ) && ! f2000cs_is_pro() ) {
		wp_send_json_error(
			array(
				'message' => __( 'XML-редактор доступний лише у Pro версії.', 'factorial2000-catalog-sync' ),
			)
		);
	}

	$sub = isset( $_POST['sub'] ) ? sanitize_key( wp_unslash( $_POST['sub'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Verified by check_ajax_referer() above.

	switch ( $sub ) {
		case 'load':
			f2000cs_xml_editor_ajax_load();
			break;
		case 'offers':
			f2000cs_xml_editor_ajax_offers();
			break;
		case 'offer_ids':
			f2000cs_xml_editor_ajax_offer_ids();
			break;
		case 'generate':
			f2000cs_xml_editor_ajax_generate();
			break;
		default:
			wp_send_json_error( array( 'message' => __( 'Невідома дія.', 'factorial2000-catalog-sync' ) ) );
	}
}

add_action( 'wp_ajax_f2000cs_xml_editor', 'f2000cs_xml_editor_ajax' );

// =========================================================================
// 5. Admin UI
// =========================================================================

/**
 * Render the XML editor card (3 columns: categories, products, conditions).
 *
 * @return void
 */
function f2000cs_xml_editor_render_card() {
	$default_url = function_exists( 'f2000cs_get_supplier_url' ) ? f2000cs_get_supplier_url( 1 ) : '';
	?>
	<div class="card f2000cs-xml-editor-card">
		<h2>🧰 Редактор вигрузок XML</h2>
		<p class="description">Сформуйте окрему вигрузку з великого XML: оберіть категорії або окремі товари, налаштуйте умови та натисніть «Сформувати XML».</p>
		<div class="f2000cs-xml-editor__hint">
			<strong><?php esc_html_e( 'Як це працює:', 'factorial2000-catalog-sync' ); ?></strong>
			<?php esc_html_e( '1. Завантажте XML з URL або файлу → 2. Позначте категорії чи окремі товари зліва → 3. Натисніть «Сформувати XML» і завантажте результат.', 'factorial2000-catalog-sync' ); ?>
		</div>

		<div class="f2000cs-xml-editor__source">
			<input type="url" id="f2000cs-xml-editor-url" class="regular-text" value="<?php echo esc_attr( $default_url ); ?>" placeholder="https://…/export.xml" />
			<span class="f2000cs-xml-editor__or"><?php esc_html_e( 'або', 'factorial2000-catalog-sync' ); ?></span>
			<input type="file" id="f2000cs-xml-editor-file" accept=".xml" />
			<button type="button" class="button button-primary" id="f2000cs-xml-editor-load"><?php esc_html_e( 'Завантажити', 'factorial2000-catalog-sync' ); ?></button>
			<button type="button" class="button" id="f2000cs-xml-editor-reset" hidden><?php esc_html_e( 'Очистити', 'factorial2000-catalog-sync' ); ?></button>
		</div>

		<div class="notice f2000cs-xml-editor__status" id="f2000cs-xml-editor-status" hidden></div>

		<div class="f2000cs-xml-editor" id="f2000cs-xml-editor" hidden>
			<div class="f2000cs-xml-editor__col">
				<div class="f2000cs-xml-editor__col-head">
					<label><input type="checkbox" class="f2000cs-xml-editor__select-all" id="f2000cs-xml-editor-select-all-cats" /> <?php esc_html_e( 'Вибрати всі', 'factorial2000-catalog-sync' ); ?></label>
					<span class="f2000cs-xml-editor__col-head-actions">
						<span class="f2000cs-xml-editor__count" id="f2000cs-xml-editor-cats-count">0</span>
						<button type="button" class="f2000cs-xml-editor__expand-btn" id="f2000cs-xml-editor-expand-all"><?php esc_html_e( 'Розгорнути', 'factorial2000-catalog-sync' ); ?></button>
						<button type="button" class="f2000cs-xml-editor__expand-btn" id="f2000cs-xml-editor-collapse-all"><?php esc_html_e( 'Згорнути', 'factorial2000-catalog-sync' ); ?></button>
					</span>
				</div>
				<div class="f2000cs-xml-editor__scroll f2000cs-xml-editor__tree" id="f2000cs-xml-editor-tree"></div>
			</div>

			<div class="f2000cs-xml-editor__col">
				<div class="f2000cs-xml-editor__col-head">
					<label><input type="checkbox" class="f2000cs-xml-editor__select-all" id="f2000cs-xml-editor-select-all-products" /> <?php esc_html_e( 'Вибрати всі', 'factorial2000-catalog-sync' ); ?></label>
					<span class="f2000cs-xml-editor__count" id="f2000cs-xml-editor-products-count">0</span>
				</div>
				<div class="f2000cs-xml-editor__products-search">
					<input type="search" id="f2000cs-xml-editor-search" placeholder="<?php esc_attr_e( 'Пошук за назвою або SKU…', 'factorial2000-catalog-sync' ); ?>" />
				</div>
				<div class="f2000cs-xml-editor__scroll" id="f2000cs-xml-editor-products">
					<p class="f2000cs-xml-editor__empty"><?php esc_html_e( 'Оберіть категорії зліва, щоб побачити товари.', 'factorial2000-catalog-sync' ); ?></p>
				</div>
				<button type="button" class="button f2000cs-xml-editor__load-more" id="f2000cs-xml-editor-load-more" hidden><?php esc_html_e( 'Показати ще', 'factorial2000-catalog-sync' ); ?></button>
			</div>

			<div class="f2000cs-xml-editor__col f2000cs-xml-editor__col--options">
				<div class="f2000cs-xml-editor__options">
					<h3><?php esc_html_e( 'Умови', 'factorial2000-catalog-sync' ); ?></h3>

					<label class="f2000cs-xml-editor__option">
						<input type="checkbox" id="f2000cs-xml-editor-instock" checked />
						<span><?php esc_html_e( 'Лише в наявності', 'factorial2000-catalog-sync' ); ?></span>
					</label>

					<div class="f2000cs-xml-editor__field">
						<label for="f2000cs-xml-editor-min-price"><?php esc_html_e( 'Мінімальна ціна', 'factorial2000-catalog-sync' ); ?></label>
						<input type="number" id="f2000cs-xml-editor-min-price" step="0.01" min="0" placeholder="0.00" />
					</div>

					<div class="f2000cs-xml-editor__field">
						<label for="f2000cs-xml-editor-max-price"><?php esc_html_e( 'Максимальна ціна', 'factorial2000-catalog-sync' ); ?></label>
						<input type="number" id="f2000cs-xml-editor-max-price" step="0.01" min="0" placeholder="0.00" />
					</div>

					<label class="f2000cs-xml-editor__option">
						<input type="checkbox" id="f2000cs-xml-editor-keep-oldprice" checked />
						<span><?php esc_html_e( 'Залишити старі ціни (oldprice)', 'factorial2000-catalog-sync' ); ?></span>
					</label>

					<p class="f2000cs-xml-editor__selected"><?php esc_html_e( 'Вибрано:', 'factorial2000-catalog-sync' ); ?> <strong id="f2000cs-xml-editor-selected">0</strong> <?php esc_html_e( 'товарів', 'factorial2000-catalog-sync' ); ?></p>

					<button type="button" class="button button-primary" id="f2000cs-xml-editor-generate" disabled><?php esc_html_e( 'Сформувати XML', 'factorial2000-catalog-sync' ); ?></button>

					<div class="f2000cs-xml-editor__result" id="f2000cs-xml-editor-result" hidden></div>
				</div>
			</div>
		</div>

		<div class="f2000cs-export-help">
			<h3>ℹ️ Як це працює</h3>
			<ol>
				<li><strong>Джерело:</strong> Вкажіть URL вигрузки постачальника або завантажте XML-файл і натисніть «Завантажити».</li>
				<li><strong>Категорії:</strong> Оберіть потрібні категорії зліва (з підкатегоріями). Праворуч з’являться товари з цих категорій.</li>
				<li><strong>Товари:</strong> За потреби зніміть або додайте окремі позиції, скористайтесь пошуком за назвою чи SKU.</li>
				<li><strong>Умови:</strong> Задайте наявність, мін./макс. ціну та чи залишати oldprice у вихідному файлі.</li>
				<li><strong>Результат:</strong> «Сформувати XML» створить новий файл лише з вибраними товарами та категоріями — без звернення до бази сайту.</li>
			</ol>
		</div>
	</div>
	<?php
}
