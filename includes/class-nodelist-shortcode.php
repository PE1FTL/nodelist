<?php

class Nodelist_Shortcode {
    public function __construct() {
        add_shortcode( 'nodelist', array( $this, 'render_nodelist_table' ) );
    }

    public function render_nodelist_table() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'nodelist';
        
        $items_per_page = 20;
        $current_page = isset( $_GET['npage'] ) ? absint( $_GET['npage'] ) : 1;
        $offset = ( $current_page - 1 ) * $items_per_page;

        $total_items = $wpdb->get_var( "SELECT COUNT(id) FROM $table_name" );
        $total_pages = ceil( $total_items / $items_per_page );

        $results = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM $table_name ORDER BY nodecall ASC LIMIT %d OFFSET %d", $items_per_page, $offset ) );
        
        ob_start();
        ?>
        <div class="wrap nodelist-container">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h3>Nodeliste</h3>
                <button id="nodelist-new-entry" class="btn btn-primary">Neuer Eintrag</button>
            </div>
            
            <div class="table-responsive">
                <table class="table table-striped table-sm table-hover">
                    <thead>
                        <tr>
                            <th>Nodecall</th>
                            <th>QTH</th>
                            <th>Locator</th>
                            <th>SysOp</th>
                            <th>HF</th>
                            <th>Telnet</th>
                            <th>AX25UDP</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ( $results ) : ?>
                            <?php foreach ( $results as $row ) : ?>
                                <tr class="nodelist-row" data-id="<?php echo esc_attr( $row->id ); ?>" style="cursor: pointer;">
                                    <td><?php echo esc_html( $row->nodecall ); ?></td>
                                    <td><?php echo esc_html( $row->qth ); ?></td>
                                    <td><?php echo esc_html( $row->locator ); ?></td>
                                    <td><?php echo esc_html( $row->sysop ); ?></td>
                                    <td><input type="checkbox" disabled <?php checked( $row->hf, 1 ); ?>></td>
                                    <td><input type="checkbox" disabled <?php checked( $row->telnet, 1 ); ?>></td>
                                    <td><input type="checkbox" disabled <?php checked( $row->ax25udp, 1 ); ?>></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else : ?>
                            <tr>
                                <td colspan="7">Keine Einträge gefunden.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <?php $this->render_pagination( $current_page, $total_pages ); ?>
        </div>
        
        <?php $this->render_modal(); ?>

        <?php
        return ob_get_clean();
    }

    private function render_pagination( $current_page, $total_pages ) {
        if ($total_pages <= 1) return;
        
        $range = 2;
        ?>
        <nav aria-label="Nodelist Navigation">
            <ul class="pagination justify-content-center">
                <?php if ($current_page > 1) : ?>
                    <li class="page-item"><a class="page-link" href="<?php echo add_query_arg('npage', 1); ?>">Anfang</a></li>
                    <li class="page-item"><a class="page-link" href="<?php echo add_query_arg('npage', $current_page - 1); ?>">&laquo;</a></li>
                <?php endif; ?>

                <?php for ( $i = 1; $i <= $total_pages; $i++ ) : ?>
                    <?php if ( $i >= $current_page - $range && $i <= $current_page + $range ) : ?>
                        <li class="page-item <?php echo ($i == $current_page) ? 'active' : ''; ?>">
                            <a class="page-link" href="<?php echo add_query_arg('npage', $i); ?>"><?php echo $i; ?></a>
                        </li>
                    <?php endif; ?>
                <?php endfor; ?>

                <?php if ($current_page < $total_pages) : ?>
                     <li class="page-item"><a class="page-link" href="<?php echo add_query_arg('npage', $current_page + 1); ?>">&raquo;</a></li>
                    <li class="page-item"><a class="page-link" href="<?php echo add_query_arg('npage', $total_pages); ?>">Ende</a></li>
                <?php endif; ?>
            </ul>
        </nav>
        <?php
    }

    private function render_modal() {
        $num1 = rand(1, 9);
        $num2 = rand(1, 9);
        $_SESSION['nodelist_captcha'] = $num1 + $num2;
        $captcha_question = "Was ist $num1 + $num2?";
        ?>
        <div class="modal fade" id="nodelist-modal" tabindex="-1" aria-labelledby="nodelistModalLabel" aria-hidden="true">
            <div class="modal-dialog modal-lg">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="nodelistModalLabel">Details</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <div id="nodelist-modal-notice" class="alert" style="display:none;"></div>
                        <form id="nodelist-form">
                            <input type="hidden" id="nodelist-id" name="id" value="">
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3"><label for="nodelist-nodecall" class="form-label">Nodecall *</label><input type="text" class="form-control" id="nodelist-nodecall" name="nodecall" required></div>
                                    <div class="mb-3"><label for="nodelist-sysopemail" class="form-label">SysOp E-Mail *</label><input type="email" class="form-control" id="nodelist-sysopemail" name="sysopemail" required></div>
                                    <div class="mb-3"><label for="nodelist-qth" class="form-label">QTH</label><input type="text" class="form-control" id="nodelist-qth" name="qth"></div>
                                    <div class="mb-3"><label for="nodelist-locator" class="form-label">Locator</label><input type="text" class="form-control" id="nodelist-locator" name="locator"></div>
                                    <div class="mb-3"><label for="nodelist-sysop" class="form-label">SysOp</label><input type="text" class="form-control" id="nodelist-sysop" name="sysop"></div>
                                    <div class="mb-3"><label for="nodelist-name" class="form-label">Name</label><input type="text" class="form-control" id="nodelist-name" name="name"></div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3 form-check"><input type="checkbox" class="form-check-input" id="nodelist-hf" name="hf"><label class="form-check-label" for="nodelist-hf">HF</label></div>
                                    <div class="mb-3"><label for="nodelist-hfportnr" class="form-label">HF Port Nr.</label><input type="number" class="form-control" id="nodelist-hfportnr" name="hfportnr"></div>
                                    <div class="mb-3 form-check"><input type="checkbox" class="form-check-input" id="nodelist-telnet" name="telnet"><label class="form-check-label" for="nodelist-telnet">Telnet</label></div>
                                    <div class="mb-3"><label for="nodelist-telneturl" class="form-label">Telnet URL</label><input type="text" class="form-control" id="nodelist-telneturl" name="telneturl"></div>
                                    <div class="mb-3"><label for="nodelist-telnetport" class="form-label">Telnet Port</label><input type="number" class="form-control" id="nodelist-telnetport" name="telnetport"></div>
                                     <div class="mb-3 form-check"><input type="checkbox" class="form-check-input" id="nodelist-ax25udp" name="ax25udp"><label class="form-check-label" for="nodelist-ax25udp">AX25 UDP</label></div>
                                    <div class="mb-3"><label for="nodelist-ax25udpurl" class="form-label">AX25 UDP URL</label><input type="text" class="form-control" id="nodelist-ax25udpurl" name="ax25udpurl"></div>
                                    <div class="mb-3"><label for="nodelist-ax25udpport" class="form-label">AX25 UDP Port</label><input type="number" class="form-control" id="nodelist-ax25udpport" name="ax25udpport"></div>
                                </div>
                                <div class="col-12"><div class="mb-3"><label for="nodelist-bemerkung" class="form-label">Bemerkung</label><textarea class="form-control" id="nodelist-bemerkung" name="bemerkung" rows="3"></textarea></div></div>
                                <div class="col-12" id="nodelist-captcha-wrapper" style="display: none;">
                                    <hr>
                                    <div class="mb-3">
                                        <label for="nodelist-captcha" class="form-label fw-bold">Sicherheitsfrage</label>
                                        <p class="mb-1">Bitte lösen Sie die folgende Aufgabe, um Spam zu vermeiden:</p>
                                        <div class="d-flex align-items-center">
                                            <span class="me-2 fs-5"><?php echo esc_html($captcha_question); ?></span>
                                            <input type="number" class="form-control" id="nodelist-captcha" name="captcha" style="width: 100px;">
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </form>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Schließen</button>
                        <button type="button" id="nodelist-submit" class="btn btn-primary">Speichern</button>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }
}