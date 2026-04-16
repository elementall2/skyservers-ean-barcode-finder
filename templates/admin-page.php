<?php
/**
 * Admin page template for SkyServers EAN Barcode Finder.
 *
 * @package SkyServersEanBarcodeFinder
 * @since   1.1.0
 *
 * @var array  $stats   Product statistics.
 * @var string $api_key UPCitemdb API key.
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="wrap skyean-wrap">
	<h1><?php esc_html_e( 'SkyServers EAN Barcode Finder', 'skyservers-ean-barcode-finder' ); ?></h1>
	<p class="skyean-subtitle"><?php esc_html_e( 'Automatically search and assign EAN/GTIN barcodes to your WooCommerce products.', 'skyservers-ean-barcode-finder' ); ?></p>

	<!-- Stats -->
	<div class="skyean-stats">
		<div class="skyean-stat-card">
			<span class="skyean-stat-number"><?php echo esc_html( $stats['total'] ); ?></span>
			<span class="skyean-stat-label"><?php esc_html_e( 'Total products', 'skyservers-ean-barcode-finder' ); ?></span>
		</div>
		<div class="skyean-stat-card skyean-stat-success">
			<span class="skyean-stat-number"><?php echo esc_html( $stats['with_ean'] ); ?></span>
			<span class="skyean-stat-label"><?php esc_html_e( 'With barcode', 'skyservers-ean-barcode-finder' ); ?></span>
		</div>
		<div class="skyean-stat-card skyean-stat-warning">
			<span class="skyean-stat-number"><?php echo esc_html( $stats['without_ean'] ); ?></span>
			<span class="skyean-stat-label"><?php esc_html_e( 'Without barcode', 'skyservers-ean-barcode-finder' ); ?></span>
		</div>
		<div class="skyean-stat-card">
			<span class="skyean-stat-number"><?php echo esc_html( $stats['percent'] ); ?>%</span>
			<span class="skyean-stat-label"><?php esc_html_e( 'Completed', 'skyservers-ean-barcode-finder' ); ?></span>
		</div>
	</div>

	<!-- API Settings -->
	<div class="skyean-section skyean-settings-section">
		<h2><?php esc_html_e( 'API Settings', 'skyservers-ean-barcode-finder' ); ?></h2>
		<p><?php esc_html_e( 'This plugin uses the following free sources to search for barcodes:', 'skyservers-ean-barcode-finder' ); ?></p>
		<ol>
			<li>
				<strong><a href="https://world.openfoodfacts.org/" target="_blank" rel="noopener noreferrer">Open Food Facts</a></strong>
				&mdash; <?php esc_html_e( 'Free open database (food products)', 'skyservers-ean-barcode-finder' ); ?>
			</li>
			<li>
				<strong><a href="https://www.upcitemdb.com/" target="_blank" rel="noopener noreferrer">UPCitemdb</a></strong>
				&mdash; <?php esc_html_e( '100 free requests/day (requires API key)', 'skyservers-ean-barcode-finder' ); ?>
			</li>
		</ol>
		<form method="post" action="">
			<?php wp_nonce_field( 'skyean_settings', 'skyean_settings_nonce' ); ?>
			<table class="form-table">
				<tr>
					<th scope="row">
						<label for="skyean_api_key"><?php esc_html_e( 'UPCitemdb API Key (optional)', 'skyservers-ean-barcode-finder' ); ?></label>
					</th>
					<td>
						<input type="text" id="skyean_api_key" name="skyean_api_key"
							   value="<?php echo esc_attr( $api_key ); ?>" class="regular-text" />
						<p class="description">
							<?php
							printf(
								/* translators: %s: URL to UPCitemdb */
								esc_html__( 'Get a free key from %s', 'skyservers-ean-barcode-finder' ),
								'<a href="https://www.upcitemdb.com/wp/docs/main/development/getting-started/" target="_blank" rel="noopener noreferrer">upcitemdb.com</a>'
							);
							?>
						</p>
					</td>
				</tr>
			</table>
			<?php submit_button( __( 'Save Settings', 'skyservers-ean-barcode-finder' ), 'primary', 'skyean_save_settings' ); ?>
		</form>
	</div>

	<!-- Products Section -->
	<div class="skyean-section">
		<h2><?php esc_html_e( 'Products', 'skyservers-ean-barcode-finder' ); ?></h2>

		<div class="skyean-toolbar">
			<div class="skyean-toolbar-left">
				<select id="skyean-filter-category">
					<option value=""><?php esc_html_e( 'All categories', 'skyservers-ean-barcode-finder' ); ?></option>
					<?php
					$categories = get_terms(
						array(
							'taxonomy'   => 'product_cat',
							'hide_empty' => true,
						)
					);
					if ( ! is_wp_error( $categories ) ) {
						foreach ( $categories as $cat ) {
							printf(
								'<option value="%s">%s</option>',
								esc_attr( $cat->term_id ),
								esc_html( $cat->name )
							);
						}
					}
					?>
				</select>
				<select id="skyean-filter-status">
					<option value="without_ean"><?php esc_html_e( 'Without barcode', 'skyservers-ean-barcode-finder' ); ?></option>
					<option value="with_ean"><?php esc_html_e( 'With barcode', 'skyservers-ean-barcode-finder' ); ?></option>
					<option value="all"><?php esc_html_e( 'All', 'skyservers-ean-barcode-finder' ); ?></option>
				</select>
				<select id="skyean-sort-by">
					<option value="title_asc"><?php esc_html_e( 'Name A→Z', 'skyservers-ean-barcode-finder' ); ?></option>
					<option value="title_desc"><?php esc_html_e( 'Name Z→A', 'skyservers-ean-barcode-finder' ); ?></option>
					<option value="sku_asc"><?php esc_html_e( 'SKU alpha A→Z', 'skyservers-ean-barcode-finder' ); ?></option>
					<option value="sku_desc"><?php esc_html_e( 'SKU alpha Z→A', 'skyservers-ean-barcode-finder' ); ?></option>
					<option value="sku_numeric_asc"><?php esc_html_e( 'SKU numeric 0→9', 'skyservers-ean-barcode-finder' ); ?></option>
					<option value="sku_numeric_desc"><?php esc_html_e( 'SKU numeric 9→0', 'skyservers-ean-barcode-finder' ); ?></option>
				</select>
				<input type="text" id="skyean-search-name" placeholder="<?php esc_attr_e( 'Search by name or SKU...', 'skyservers-ean-barcode-finder' ); ?>" class="regular-text" />
				<button id="skyean-filter-btn" class="button"><?php esc_html_e( 'Filter', 'skyservers-ean-barcode-finder' ); ?></button>
			</div>
			<div class="skyean-toolbar-right">
				<select id="skyean-per-page">
					<option value="20">20 / <?php esc_html_e( 'page', 'skyservers-ean-barcode-finder' ); ?></option>
					<option value="50">50 / <?php esc_html_e( 'page', 'skyservers-ean-barcode-finder' ); ?></option>
					<option value="100">100 / <?php esc_html_e( 'page', 'skyservers-ean-barcode-finder' ); ?></option>
					<option value="200">200 / <?php esc_html_e( 'page', 'skyservers-ean-barcode-finder' ); ?></option>
				</select>
				<button id="skyean-bulk-search-btn" class="button button-primary">
					<?php esc_html_e( 'Auto-search all', 'skyservers-ean-barcode-finder' ); ?>
				</button>
			</div>
		</div>

		<!-- Bulk progress -->
		<div id="skyean-bulk-progress" style="display:none;">
			<div class="skyean-progress-bar">
				<div class="skyean-progress-fill" style="width: 0%"></div>
			</div>
			<div class="skyean-progress-text">
				<span id="skyean-progress-current">0</span> / <span id="skyean-progress-total">0</span>
				<?php esc_html_e( 'products processed', 'skyservers-ean-barcode-finder' ); ?>
				| <span id="skyean-progress-found" class="skyean-text-success">0 <?php esc_html_e( 'found', 'skyservers-ean-barcode-finder' ); ?></span>
				| <span id="skyean-progress-notfound" class="skyean-text-warning">0 <?php esc_html_e( 'not found', 'skyservers-ean-barcode-finder' ); ?></span>
			</div>
			<button id="skyean-bulk-stop-btn" class="button"><?php esc_html_e( 'Stop', 'skyservers-ean-barcode-finder' ); ?></button>
		</div>

		<!-- Products table -->
		<table class="wp-list-table widefat fixed striped" id="skyean-products-table">
			<thead>
				<tr>
					<th class="skyean-col-thumb"><?php esc_html_e( 'Image', 'skyservers-ean-barcode-finder' ); ?></th>
					<th class="skyean-col-name"><?php esc_html_e( 'Product', 'skyservers-ean-barcode-finder' ); ?></th>
					<th class="skyean-col-sku"><?php esc_html_e( 'SKU', 'skyservers-ean-barcode-finder' ); ?></th>
					<th class="skyean-col-ean"><?php esc_html_e( 'Barcode (GTIN/EAN)', 'skyservers-ean-barcode-finder' ); ?></th>
					<th class="skyean-col-actions"><?php esc_html_e( 'Actions', 'skyservers-ean-barcode-finder' ); ?></th>
				</tr>
			</thead>
			<tbody id="skyean-products-body">
				<tr>
					<td colspan="5" class="skyean-loading"><?php esc_html_e( 'Loading products...', 'skyservers-ean-barcode-finder' ); ?></td>
				</tr>
			</tbody>
		</table>

		<div class="skyean-pagination" id="skyean-pagination"></div>
	</div>

	<!-- Activity Log -->
	<div class="skyean-section">
		<h2><?php esc_html_e( 'Activity Log', 'skyservers-ean-barcode-finder' ); ?></h2>
		<div id="skyean-log" class="skyean-log"></div>
	</div>

	<div class="skyean-footer">
		<?php
		printf(
			/* translators: 1: version number, 2: author link */
			esc_html__( 'Version %1$s | By %2$s', 'skyservers-ean-barcode-finder' ),
			esc_html( SKYEAN_VERSION ),
			'<a href="https://sky-servers.com" target="_blank" rel="noopener noreferrer">Sky Servers SRL</a>'
		);
		?>
	</div>
</div>
