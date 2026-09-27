<?php
namespace BizimTheme\Integrations\GiveWP;

use BizimTheme\Core\ModuleInterface;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * "Ek Bilgiler & Ülke" admin editor - the fields GiveWP v3's React builder hides
 * (country, slider flag and the single-page SCF fields), in one compact screen.
 */
final class AdminExtras implements ModuleInterface {

	private const SLIDER_TAX  = 'give_slider';
	private const SLIDER_TERM = 'slider';

	/** Rows refused on the last save because the page was out of date. */
	private int $stale = 0;

	public function register(): void {
		add_action( 'admin_menu', array( $this, 'menu' ) );
	}

	public function menu(): void {
		add_submenu_page(
			'edit.php?post_type=give_forms',
			'Ek Bilgiler & Ülke',
			'Ek Bilgiler & Ülke',
			'manage_options',
			'bizim-form-extras',
			array( $this, 'render' )
		);
	}

	private function save(): int {
		if ( ! isset( $_POST['bizim_extras_nonce'] ) || ! wp_verify_nonce( $_POST['bizim_extras_nonce'], 'bizim_extras_save' ) ) {
			return -1;
		}
		$rows  = isset( $_POST['bizim'] ) ? (array) wp_unslash( $_POST['bizim'] ) : array();
		$saved       = 0;
		$this->stale = 0;
		foreach ( $rows as $fid => $data ) {
			$fid = (int) $fid;
			if ( ! $fid || 'give_forms' !== get_post_type( $fid ) ) {
				continue;
			}

			// Refuse a row whose stored values changed since this page was drawn.
			// The screen posts EVERY form on every save, so a tab left open - or one
			// drawn before a fix - would otherwise write its stale contents over
			// newer text. This is what kept overwriting the translations.
			if ( ! isset( $data['sig'] ) || $data['sig'] !== $this->row_signature( $fid ) ) {
				$this->stale++;
				continue;
			}

			// Resolve by term id in the form's OWN language. Never pass a slug here:
			// a translated term keeps the slug of its original, so 'filistin' matches
			// both the Turkish and the Arabic term and WordPress picks whichever the
			// current admin language resolves to - silently re-pointing Arabic forms
			// at Turkish terms and emptying every Arabic country query.
			$ulke    = isset( $data['ulke'] ) ? sanitize_title( $data['ulke'] ) : '';
			$ulke_id = $ulke ? $this->term_in_post_language( $ulke, 'give_country', $fid ) : 0;
			wp_set_object_terms( $fid, $ulke_id ? array( $ulke_id ) : array(), 'give_country', false );

			// Slider flag -> taxonomy term, so the Bricks slider can use a native tax query.
			$in_slider = ! empty( $data['slider'] );
			$slider_id = $in_slider ? $this->term_in_post_language( self::SLIDER_TERM, self::SLIDER_TAX, $fid ) : 0;
			wp_set_object_terms( $fid, $slider_id ? array( $slider_id ) : array(), self::SLIDER_TAX, false );

			if ( function_exists( 'update_field' ) ) {
				$b = isset( $data['benef'] ) ? trim( $data['benef'] ) : '';
				update_field( 'beneficiary_count', ( '' === $b ? '' : (int) $b ), $fid );
				update_field( 'completed_projects_text', isset( $data['completed'] ) ? sanitize_text_field( $data['completed'] ) : '', $fid );
				update_field( 'impact_report', isset( $data['impact'] ) ? wp_kses_post( $data['impact'] ) : '', $fid );

				$lines = isset( $data['neden'] ) ? preg_split( '/\r\n|\r|\n/', (string) $data['neden'] ) : array();
				$list  = array();
				foreach ( (array) $lines as $line ) {
					$line = sanitize_text_field( trim( $line ) );
					if ( '' !== $line ) {
						$list[] = array( 'madde_metni' => $line );
					}
				}
				update_field( 'neden_biz_listesi', $list, $fid );

				update_field( 'highlight_title', isset( $data['hltitle'] ) ? sanitize_text_field( $data['hltitle'] ) : '', $fid );
				update_field( 'highlight_text', isset( $data['hltext'] ) ? wp_kses_post( $data['hltext'] ) : '', $fid );
			}
			$saved++;
		}
		if ( function_exists( 'give_delete_donation_form_transients' ) ) {
			give_delete_donation_form_transients();
		}
		return $saved;
	}

	/**
	 * The term with this slug that belongs to the post's own language.
	 *
	 * WPML gives a translated term the same slug as its original, so looking a
	 * term up by slug is ambiguous the moment a second language exists. Read the
	 * candidates straight from the database (get_term_by is language-filtered),
	 * then pick the one matching the post, falling back to the original.
	 */
	/**
	 * Fingerprint of everything this screen can write for one form.
	 *
	 * Rendered into the page as a hidden field and re-checked on save. If the
	 * stored values moved in between - another tab, another editor, a repair
	 * script - the row is refused rather than overwritten. Read raw from
	 * give_formmeta so no display filter can influence the fingerprint.
	 */
	private function row_signature( int $form_id ): string {
		global $wpdb;
		$parts = array();

		$keys = array( 'beneficiary_count', 'completed_projects_text', 'impact_report', 'highlight_title', 'highlight_text', 'neden_biz_listesi' );
		for ( $i = 0; $i < 12; $i++ ) {
			$keys[] = 'neden_biz_listesi_' . $i . '_madde_metni';
		}

		// One read, not one per field - this runs for every form on the screen.
		$table = $wpdb->prefix . 'give_formmeta';
		$rows  = $wpdb->get_results( $wpdb->prepare( "SELECT meta_key, meta_value FROM {$table} WHERE form_id = %d ORDER BY meta_key", $form_id ), ARRAY_A );
		$stored = array();
		foreach ( (array) $rows as $row ) {
			$stored[ (string) $row['meta_key'] ] = (string) $row['meta_value'];
		}
		foreach ( $keys as $key ) {
			$parts[] = $key . '=' . ( $stored[ $key ] ?? '' );
		}

		foreach ( array( 'give_country', self::SLIDER_TAX ) as $taxonomy ) {
			$ids = $wpdb->get_col(
				$wpdb->prepare(
					"SELECT tx.term_id FROM {$wpdb->term_relationships} tr
					   INNER JOIN {$wpdb->term_taxonomy} tx ON tx.term_taxonomy_id = tr.term_taxonomy_id
					  WHERE tr.object_id = %d AND tx.taxonomy = %s ORDER BY tx.term_id",
					$form_id,
					$taxonomy
				)
			);
			$parts[] = $taxonomy . '=' . implode( ',', (array) $ids );
		}

		return md5( implode( '|', $parts ) );
	}
	private function term_in_post_language( string $slug, string $taxonomy, int $post_id ): int {
		global $wpdb;

		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT t.term_id, tx.term_taxonomy_id
				   FROM {$wpdb->terms} t
				   INNER JOIN {$wpdb->term_taxonomy} tx ON tx.term_id = t.term_id
				  WHERE t.slug = %s AND tx.taxonomy = %s",
				$slug,
				$taxonomy
			),
			ARRAY_A
		);
		if ( ! $rows ) {
			return 0;
		}
		$ids = array_map( static fn( $r ) => (int) $r['term_id'], $rows );
		if ( 1 === count( $rows ) ) {
			return $ids[0];
		}

		$details = apply_filters( 'wpml_post_language_details', null, $post_id );
		$language = is_array( $details ) && ! empty( $details['language_code'] ) ? (string) $details['language_code'] : '';
		if ( '' === $language ) {
			return $ids[0];
		}

		foreach ( $rows as $row ) {
			// WPML keys taxonomy elements by term_taxonomy_id, not term_id.
			$term_language = apply_filters(
				'wpml_element_language_code',
				null,
				array( 'element_id' => (int) $row['term_taxonomy_id'], 'element_type' => 'tax_' . $taxonomy )
			);
			if ( $term_language === $language ) {
				return (int) $row['term_id'];
			}
		}

		$mapped = apply_filters( 'wpml_object_id', $ids[0], $taxonomy, true, $language );

		return $mapped ? (int) $mapped : $ids[0];
	}
	public function render(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$saved = $this->save();

		$forms     = get_posts( array( 'post_type' => 'give_forms', 'post_status' => array( 'publish', 'draft' ), 'numberposts' => -1, 'orderby' => 'title', 'order' => 'ASC' ) );
		$countries = get_terms( array( 'taxonomy' => 'give_country', 'hide_empty' => false ) );
		$countries = is_wp_error( $countries ) ? array() : $countries;

		echo '<div class="wrap bizim-extras">';
		echo '<h1>Ek Bilgiler &amp; Ülke</h1>';

		if ( $saved >= 0 ) {
			echo '<div class="notice notice-success is-dismissible"><p><strong>Kaydedildi.</strong> ' . (int) $saved . ' kampanya güncellendi.</p></div>';
			if ( $this->stale > 0 ) {
				echo '<div class="notice notice-warning is-dismissible"><p><strong>' . (int) $this->stale . ' kampanya atlandı.</strong> Bu satırlar bu sayfa açıldıktan sonra başka bir yerde değişti; eski içerikle üzerine yazılmalarını önledik. Sayfayı yenileyip tekrar deneyin.</p></div>';
			}
		}

		echo '<p class="bizim-extras__intro">GiveWP görsel form oluşturucusu bu alanları göstermez. Kampanyanın <strong>ülkesini</strong>, <strong>ana sayfa slider</strong> durumunu ve tekli sayfa içeriğini buradan yönetin. Bayrak ikonları: Bağış Formları &rarr; Ülkeler.</p>';

		echo '<div class="bizim-extras__toolbar">';
		echo '<input type="search" id="bizim-extras-search" placeholder="Kampanya ara..." autocomplete="off">';
		echo '<span class="bizim-extras__count"><span id="bizim-extras-shown">' . count( $forms ) . '</span> / ' . count( $forms ) . ' kampanya</span>';
		echo '<span class="bizim-extras__spacer"></span>';
		echo '<button type="button" class="button" id="bizim-extras-toggle">Tümünü aç / kapat</button>';
		echo '</div>';

		echo '<form method="post" id="bizim-extras-form">';
		wp_nonce_field( 'bizim_extras_save', 'bizim_extras_nonce' );

		foreach ( $forms as $form ) {
			$fid       = (int) $form->ID;
			$terms     = get_the_terms( $fid, 'give_country' );
			$curslug   = ( $terms && ! is_wp_error( $terms ) ) ? $terms[0]->slug : '';
			$curname   = ( $terms && ! is_wp_error( $terms ) ) ? $terms[0]->name : '';
			$in_slider = has_term( self::SLIDER_TERM, self::SLIDER_TAX, $fid );
			$benef     = (string) get_field( 'beneficiary_count', $fid );
			$completed = (string) get_field( 'completed_projects_text', $fid );
			$impact    = (string) get_field( 'impact_report', $fid );
			$hltitle   = (string) get_field( 'highlight_title', $fid );
			$hltext    = (string) get_field( 'highlight_text', $fid );

			$rows_list = get_field( 'neden_biz_listesi', $fid );
			$neden     = '';
			if ( is_array( $rows_list ) ) {
				$tmp = array();
				foreach ( $rows_list as $row ) {
					$tmp[] = (string) ( $row['madde_metni'] ?? '' );
				}
				$neden = implode( "\n", array_filter( $tmp ) );
			}

			$title  = $form->post_title !== '' ? $form->post_title : '(başlıksız)';
			$search = strtolower( $title . ' ' . $curname . ' ' . $fid );

			echo '<details class="bizim-card" data-search="' . esc_attr( $search ) . '">';
			echo '<summary class="bizim-card__head">';
			echo '<span class="bizim-card__title">' . esc_html( $title ) . '</span>';
			echo '<span class="bizim-card__id">#' . $fid . '</span>';
			if ( 'draft' === $form->post_status ) {
				echo '<span class="bizim-badge bizim-badge--draft">Taslak</span>';
			}
			echo '<span class="bizim-badge bizim-badge--country" data-empty="' . ( $curname ? '0' : '1' ) . '">' . esc_html( $curname ? $curname : 'Ülke yok' ) . '</span>';
			echo '<span class="bizim-badge bizim-badge--slider" data-on="' . ( $in_slider ? '1' : '0' ) . '">' . ( $in_slider ? 'Slider' : '' ) . '</span>';
			echo '</summary>';

			echo '<div class="bizim-card__body">';
			echo '<input type="hidden" name="bizim[' . $fid . '][sig]" value="' . esc_attr( $this->row_signature( $fid ) ) . '">';

			echo '<div class="bizim-grid">';
			echo '<label class="bizim-field"><span>Ülke</span><select name="bizim[' . $fid . '][ulke]"><option value="">— Yok —</option>';
			foreach ( $countries as $term ) {
				echo '<option value="' . esc_attr( $term->slug ) . '" ' . selected( $curslug, $term->slug, false ) . '>' . esc_html( $term->name ) . '</option>';
			}
			echo '</select></label>';

			echo '<label class="bizim-field"><span>Faydalanıcı Sayısı</span><input type="number" name="bizim[' . $fid . '][benef]" value="' . esc_attr( $benef ) . '"></label>';
			echo '<label class="bizim-field"><span>Tamamlanan Projeler Rozeti</span><input type="text" name="bizim[' . $fid . '][completed]" value="' . esc_attr( $completed ) . '" placeholder="ör. 120+ tamamlanan proje"></label>';
			echo '</div>';

			echo '<label class="bizim-check"><input type="checkbox" name="bizim[' . $fid . '][slider]" value="1" ' . checked( $in_slider, true, false ) . '><span><strong>Ana sayfa slider’ında göster</strong><em>İşaretlenirse bu kampanya ana sayfadaki büyük slider’da görünür.</em></span></label>';

			echo '<label class="bizim-field bizim-field--full"><span>Neden Biz Listesi <em>(her satır bir madde)</em></span><textarea name="bizim[' . $fid . '][neden]" rows="4" placeholder="Her satıra bir madde yazın">' . esc_textarea( $neden ) . '</textarea></label>';

			echo '<label class="bizim-field bizim-field--full"><span>Vurgu Kutusu Başlığı</span><input type="text" name="bizim[' . $fid . '][hltitle]" value="' . esc_attr( $hltitle ) . '" placeholder="ör. Bağış Payının Değeri"></label>';
			echo '<label class="bizim-field bizim-field--full"><span>Vurgu Kutusu Metni <em>(boş bırakılırsa kutu görünmez)</em></span><textarea name="bizim[' . $fid . '][hltext]" rows="4">' . esc_textarea( $hltext ) . '</textarea></label>';
			echo '<label class="bizim-field bizim-field--full"><span>Etki Raporu / Detaylar</span><textarea name="bizim[' . $fid . '][impact]" rows="5">' . esc_textarea( $impact ) . '</textarea></label>';

			echo '<p class="bizim-card__links"><a href="' . esc_url( get_permalink( $fid ) ) . '" target="_blank">Sayfayı görüntüle</a> &middot; <a href="' . esc_url( get_edit_post_link( $fid ) ) . '">GiveWP’de düzenle</a></p>';

			echo '</div></details>';
		}

		echo '<div class="bizim-extras__save">';
		submit_button( 'Tüm Değişiklikleri Kaydet', 'primary large', 'submit', false );
		echo '</div>';
		echo '</form></div>';

		$this->assets();
	}

	private function assets(): void {
		?>
		<style>
		.bizim-extras__intro{max-width:820px;color:#50575e}
		.bizim-extras__toolbar{position:sticky;top:32px;z-index:20;display:flex;align-items:center;gap:12px;background:#fff;border:1px solid #dcdcde;border-radius:10px;padding:10px 14px;margin:16px 0}
		.bizim-extras__toolbar input[type=search]{min-width:320px;padding:6px 10px}
		.bizim-extras__spacer{flex:1}
		.bizim-extras__count{color:#646970;font-size:12px}
		.bizim-card{background:#fff;border:1px solid #dcdcde;border-radius:10px;margin:0 0 8px;overflow:hidden}
		.bizim-card[open]{box-shadow:0 2px 8px rgba(0,0,0,.06)}
		.bizim-card__head{display:flex;align-items:center;gap:10px;padding:12px 16px;cursor:pointer;list-style:none;user-select:none}
		.bizim-card__head::-webkit-details-marker{display:none}
		.bizim-card__head:hover{background:#f6f7f7}
		.bizim-card__title{font-weight:600;font-size:14px}
		.bizim-card__id{color:#8c8f94;font-size:12px}
		.bizim-badge{font-size:11px;font-weight:600;padding:3px 10px;border-radius:999px;background:#eff6ff;color:#244594}
		.bizim-badge--country[data-empty="1"]{background:#fdecea;color:#a4231d}
		.bizim-badge--draft{background:#fef3c7;color:#8a5a00}
		.bizim-badge--slider[data-on="0"]{display:none}
		.bizim-badge--slider[data-on="1"]{background:#244594;color:#fff}
		.bizim-card__body{padding:4px 16px 16px;border-top:1px solid #f0f0f1}
		.bizim-grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:14px;margin:14px 0}
		.bizim-field{display:flex;flex-direction:column;gap:5px}
		.bizim-field>span{font-size:12px;font-weight:600;color:#1d2327}
		.bizim-field>span em{font-weight:400;color:#787c82}
		.bizim-field input,.bizim-field select,.bizim-field textarea{width:100%}
		.bizim-field--full{margin:0 0 14px}
		.bizim-check{display:flex;gap:10px;align-items:flex-start;background:#f6f7f7;border:1px solid #e0e0e0;border-radius:8px;padding:10px 12px;margin:0 0 14px}
		.bizim-check em{display:block;font-style:normal;color:#787c82;font-size:12px}
		.bizim-card__links{margin:0;font-size:12px}
		.bizim-extras__save{position:sticky;bottom:0;background:#fff;border-top:1px solid #dcdcde;padding:12px 0;margin-top:16px}
		@media (max-width:960px){.bizim-grid{grid-template-columns:1fr}.bizim-extras__toolbar input[type=search]{min-width:0;flex:1}}
		</style>
		<script>
		(function(){
			var q = document.getElementById('bizim-extras-search');
			var shown = document.getElementById('bizim-extras-shown');
			var cards = Array.prototype.slice.call(document.querySelectorAll('.bizim-card'));
			if(q){
				q.addEventListener('input', function(){
					var v = q.value.toLowerCase().trim(), n = 0;
					cards.forEach(function(c){
						var hit = !v || (c.getAttribute('data-search')||'').indexOf(v) > -1;
						c.style.display = hit ? '' : 'none';
						if(hit){ n++; }
					});
					if(shown){ shown.textContent = n; }
				});
			}
			var t = document.getElementById('bizim-extras-toggle');
			if(t){
				t.addEventListener('click', function(){
					var anyClosed = cards.some(function(c){ return !c.open; });
					cards.forEach(function(c){ c.open = anyClosed; });
				});
			}
		})();
		</script>
		<?php
	}
}