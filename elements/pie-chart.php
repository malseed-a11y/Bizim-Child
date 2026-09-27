<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

class Bizim_Pie_Chart extends \Bricks\Element {
    public $category = 'general';
    public $name     = 'bizim-pie-chart';
    public $icon     = 'ti-pie-chart';

    public function get_label() {
        return esc_html__( 'Bizim – Bağış Dağılımı (Donut)', 'bizim-child' );
    }

    public function set_controls() {
        $this->controls['size']        = array( 'label' => 'Boyut (px)', 'type' => 'number', 'default' => 260 );
        $this->controls['thickness']   = array( 'label' => 'Kalınlık (px)', 'type' => 'number', 'default' => 34 );
        $this->controls['showPercent'] = array( 'label' => 'Yüzdeleri göster', 'type' => 'checkbox', 'default' => true );
    }

    private function get_data() {
        $out = array();
        $q = new \WP_Query( array(
            'post_type'      => 'allocation_stat',
            'post_status'    => 'publish',
            'posts_per_page' => -1,
            'orderby'        => 'menu_order',
            'order'          => 'ASC',
            'no_found_rows'  => true,
        ) );
        foreach ( $q->posts as $p ) {
            $color = trim( (string) get_post_meta( $p->ID, 'stat_color', true ) );
            $out[] = array(
                'title' => get_the_title( $p ),
                'pct'   => (float) get_post_meta( $p->ID, 'stat_percentage', true ),
                'color' => $color !== '' ? $color : '#244594',
            );
        }
        wp_reset_postdata();
        return $out;
    }

    private function color_css( $c ) {
        if ( $c === '' ) { return '#244594'; }
        if ( $c[0] === '#' || strpos( $c, 'rgb' ) === 0 || strpos( $c, 'var(' ) === 0 ) { return $c; }
        return 'var(--bricks-color-' . preg_replace( '/[^a-z0-9]/i', '', $c ) . ')';
    }

    public function render() {
        $settings  = $this->settings;
        $size      = isset( $settings['size'] ) ? (int) $settings['size'] : 260;
        $thickness = isset( $settings['thickness'] ) ? (int) $settings['thickness'] : 34;
        $show_pct  = ! isset( $settings['showPercent'] ) || ! empty( $settings['showPercent'] );
        if ( $size < 60 ) { $size = 60; }
        $data = $this->get_data();

        $this->set_attribute( '_root', 'class', 'bizim-pie' );

        if ( empty( $data ) ) {
            echo '<div ' . $this->render_attributes( '_root' ) . '><p>' . esc_html__( 'Henüz dağılım verisi yok.', 'bizim-child' ) . '</p></div>';
            return;
        }

        $total = 0.0;
        foreach ( $data as $d ) { $total += $d['pct']; }
        if ( $total <= 0 ) { $total = 100; }

        $cx = 21; $cy = 21; $r = 15.91549431;
        $sw = ( $thickness / $size ) * 42.0;
        if ( $sw < 1 ) { $sw = 1; }
        if ( $sw > 20 ) { $sw = 20; }

        // Distance gap between the outer edge of the pie ring and text labels
        $text_gap = 5.2; 
        $label_r  = $r + ( $sw / 2.0 ) + $text_gap;

        $labels = array();
        foreach ( $data as $d ) { 
            $labels[] = $d['title'] . ' ' . rtrim( rtrim( number_format( $d['pct'], 2, '.', '' ), '0' ), '.' ) . '%'; 
        }
        $aria = esc_attr( implode( ', ', $labels ) );

        // Tightened viewBox (-6 -6 54 54) makes the pie noticeably bigger while preserving label margins
        $svg  = '<svg class="bizim-pie__svg" viewBox="-6 -6 54 54" role="img" aria-label="' . $aria . '" style="width:' . $size . 'px;height:' . $size . 'px;max-width:100%;">';
        $svg .= '<circle cx="21" cy="21" r="' . $r . '" fill="none" stroke="var(--bricks-color-0a5371)" stroke-width="' . $sw . '"></circle>';
        
        $cum = 0.0; 
        $text = '';

        foreach ( $data as $d ) {
            $seg = ( $d['pct'] / $total ) * 100.0;
            $dash = round( $seg, 3 ) . ' ' . round( 100 - $seg, 3 );
            $offset = round( 25 - $cum, 3 );
            $stroke = esc_attr( $this->color_css( $d['color'] ) );
            
            $svg .= '<circle class="bizim-pie__seg" cx="21" cy="21" r="' . $r . '" fill="none" stroke="' . $stroke . '" stroke-width="' . $sw . '" stroke-dasharray="' . $dash . '" stroke-dashoffset="' . $offset . '"></circle>';
            
            if ( $show_pct && $seg >= 1 ) {
                $mid   = $cum + $seg / 2.0;
                $theta = 2 * M_PI * $mid / 100.0;
                $lx    = round( $cx + $label_r * sin( $theta ), 3 );
                $ly    = round( $cy - $label_r * cos( $theta ), 3 );
                $pl    = rtrim( rtrim( number_format( $d['pct'], 1, '.', '' ), '0' ), '.' ) . '%';
                
                // Set font-weight to 400 (regular/lighter) and font-size to 3px
                $text .= '<text x="' . $lx . '" y="' . $ly . '" class="bizim-pie__label" text-anchor="middle" dominant-baseline="central" style="font-size:3px;font-weight:400;fill:#244594;">' . $pl . '</text>';
            }
            $cum += $seg;
        }
        $svg .= $text . '</svg>';

        echo '<div ' . $this->render_attributes( '_root' ) . '>' . $svg . '</div>';
    }
}