<?php
defined( 'CLEANUP_TESTING' ) || exit;
require '/cleanup-initial/import.php';
wp_set_current_user( 0 );
$denied = false;
try { cleanup_import_initial_pack(); } catch ( RuntimeException $e ) { $denied = true; }
check( $denied, 'initial import denies anonymous user' );
wp_set_current_user( 1 );
$imported = cleanup_import_initial_pack();
check( count( $imported ) === 7, 'initial pack imports four articles and three companies' );
$drafts = true; $blocked = true; $claims_pending = true;
foreach ( $imported as $item ) {
    $drafts = $drafts && 'draft' === get_post_status( $item['id'] );
    $blocked = $blocked && is_wp_error( cleanup_approve( $item['id'], cleanup_fingerprint( $item['id'] ) ) );
    if ( 'article' === get_post_type( $item['id'] ) ) {
        $claims = get_post_meta( $item['id'], '_cleanup_ai_claims', true );
        $claims_pending = $claims_pending && $claims && 'needs_review' === $claims[0]['status'];
    }
}
check( $drafts && $blocked && $claims_pending, 'imported drafts require human review and company disclosures' );
$first = $imported[0]['id'];
wp_update_post( array( 'ID' => $first, 'post_title' => 'EDITOR CHANGE' ) );
$again = cleanup_import_initial_pack();
check( array_column( $again, 'id' ) === array_column( $imported, 'id' ) && 'EDITOR CHANGE' === get_the_title( $first ), 'repeat import preserves ids and editorial changes' );
