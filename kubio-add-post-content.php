<?php
/**
 * Plugin Name: Kubio - Add Post Content
 * Description: Adds the post content block to the page and full-width templates. You can run it from your WP Admin Panel > Tools > Kubio Add Post Content page.
 * Version: 1.0
 * Author: ExtendStudio
 * License: GPLv3 or later
 * License URI: https://www.gnu.org/licenses/gpl-3.0.en.html
 */

if (!defined('ABSPATH')) {
	exit;
}

add_action('admin_menu', function () {
	add_management_page(
		'Kubio Add Post Content',
		'Kubio Add Post Content',
		'manage_options',
		'kubio-add-post-content',
		'kubio_apc_render_admin_page'
	);
});

function kubio_apc_render_admin_page() {
	if (!current_user_can('manage_options')) {
		wp_die('You do not have permission to access this page.');
	}

	$message = '';

	if (
		isset($_POST['kubio_apc_run_replacement']) &&
		check_admin_referer('kubio_apc_run_replacement_action', 'kubio_apc_nonce')
	) {
		$result = kubio_apc_replace_template_content();
		$message = sprintf(
			'Updated %d template(s). Skipped %d template(s). Active theme slug: %s.',
			$result['updated'],
			$result['skipped'],
			$result['theme_slug']
		);
	}
	?>
	<div class="wrap">
		<h1>Kubio - Add Post Content</h1>

		<?php if ($message) : ?>
			<div class="notice notice-success is-dismissible">
				<p><?php echo esc_html($message); ?></p>
			</div>
		<?php endif; ?>

		<p>
			Adds the post content block back to the <code>page</code> and <code>full-width</code> templates. <b>We would recommend creating a backup before running it.</b>
		</p>

		<form method="post">
			<?php wp_nonce_field('kubio_apc_run_replacement_action', 'kubio_apc_nonce'); ?>
			<?php submit_button('Start the process', 'primary', 'kubio_apc_run_replacement'); ?>
		</form>
	</div>
	<?php
	
	$target_slugs = ['page'];
	$theme_slug = get_stylesheet();

	$templates = get_posts([
		'post_type'      => 'wp_template',
		'post_status'    => 'any',
		'posts_per_page' => -1,
		'name__in'       => $target_slugs,
		'tax_query'      => [
			[
				'taxonomy' => 'wp_theme',
				'field'    => 'slug',
				'terms'    => $theme_slug,
			],
		],
	]);

	$updated = 0;
	$skipped = 0;

	foreach ($templates as $template) {
		if (!in_array($template->post_name, $target_slugs, true)) {
			$skipped++;
			continue;
		}
		
		$blocks = parse_blocks($template->post_content);
		$blocks = array_values( array_filter( $blocks, function( $block ) {
			return in_array( $block['blockName'], [ 'kubio/header', 'kubio/footer' ], TRUE );
		} ) );
		
		$headers = array_values( array_filter( $blocks, function( $block ) {
			return 'kubio/header' === $block['blockName'];
		} ) );
		
		$footers = array_values( array_filter( $blocks, function( $block ) {
			return 'kubio/footer' === $block['blockName'];
		} ) );
		
		$postConent = parse_blocks('\n<!-- wp:post-content /-->\n');
		
		$new_content = array_merge( $headers, $postConent, $footers );
		$new_content = serialize_blocks( $new_content );
	}
		
}

function kubio_apc_replace_template_content() {
	$target_slugs = ['page', 'full-width'];
	$theme_slug = get_stylesheet();

	$templates = get_posts([
		'post_type'      => 'wp_template',
		'post_status'    => 'any',
		'posts_per_page' => -1,
		'name__in'       => $target_slugs,
		'tax_query'      => [
			[
				'taxonomy' => 'wp_theme',
				'field'    => 'slug',
				'terms'    => $theme_slug,
			],
		],
	]);

	$updated = 0;
	$skipped = 0;

	foreach ($templates as $template) {
		if (!in_array($template->post_name, $target_slugs, true)) {
			$skipped++;
			continue;
		}
		
		$blocks = parse_blocks($template->post_content);
		$blocks = array_values( array_filter( $blocks, function( $block ) {
			return in_array( $block['blockName'], [ 'kubio/header', 'kubio/footer' ], TRUE );
		} ) );
		
		$headers = array_values( array_filter( $blocks, function( $block ) {
			return 'kubio/header' === $block['blockName'];
		} ) );
		
		$footers = array_values( array_filter( $blocks, function( $block ) {
			return 'kubio/footer' === $block['blockName'];
		} ) );
		
		$postConent = parse_blocks('<!-- wp:post-content /-->');
		
		$new_content = array_merge( $headers, $postConent, $footers );
		$new_content = serialize_blocks( $new_content );

		$result = wp_update_post([
			'ID'           => $template->ID,
			'post_content' => $new_content,
		], true);

		if (is_wp_error($result)) {
			$skipped++;
		} else {
			$updated++;
		}
	}

	return [
		'updated'    => $updated,
		'skipped'    => $skipped,
		'theme_slug' => $theme_slug,
	];
}