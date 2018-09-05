<?php

header( "Content-Type: application/json" );

function edie( $msg ) {
  $json = [ 'ok' => false, 'msg' => $msg ];
  die( json_encode( $json ) );
}

if( !array_key_exists( 'client_id', $_POST ) || !strlen( trim( $_POST[ 'client_id' ] ) ) ) {
  edie( 'Client ID not provided' );
}

if( !array_key_exists( 'secret', $_POST ) || !strlen( trim( $_POST[ 'secret' ] ) ) ) {
  edie( 'Secret not provided' );
}

if( !array_key_exists( 'webhook_url', $_POST ) || !strlen( trim( $_POST[ 'webhook_url' ] ) ) ) {
  edie( 'Webhook URL not provided' );
}

$client_id = trim( $_POST[ 'client_id' ] );
$secret = trim( $_POST[ 'secret' ] );
$webhook_url = trim( $_POST[ 'webhook_url' ] );

// Get an access token
$curl = curl_init( 'https://api.paypal.com/v1/oauth2/token' );
if( !$curl ) {
  edie( 'Internal error (curl_init() failed)' );
}

if( !curl_setopt( $curl, CURLOPT_RETURNTRANSFER, true ) ||
    !curl_setopt( $curl, CURLOPT_POST, true ) ||
    !curl_setopt( $curl, CURLOPT_POSTFIELDS, 'grant_type=client_credentials' ) ||
    !curl_setopt( $curl, CURLOPT_USERPWD, $client_id . ':' . $secret ) ) {
  edie( 'Internal error (curl_setopt() failed)' );
}

$response = curl_exec( $curl );
if( !$response ) {
  edie( 'Access token request to PayPal failed: ' . curl_error( $curl ) );
}

$json = json_decode( $response );
if( NULL === $json ) {
  edie( 'Failed to decode JSON response from PayPal while requesting access token' );
}

if( !property_exists( $json, 'access_token' ) || !strlen( trim( $json->access_token ) ) ) {
  edie( 'No access token present in response from PayPal' );
}

$access_token = trim( $json->access_token );

curl_close( $curl );

$curl = curl_init( 'https://api.paypal.com/v1/notifications/webhooks' );

if( !$curl ) {
  edie( 'Internal error (curl_init() failed)' );
}

$req = [
  'url' => $webhook_url,
  'event_types' => [
    [
      'name' => 'CUSTOMER.DISPUTE.CREATED'
    ],
    [
      'name' => 'CUSTOMER.DISPUTE.UPDATED'
    ],
    [
      'name' => 'CUSTOMER.DISPUTE.RESOLVED'
    ]
  ]
];

if( !curl_setopt( $curl, CURLOPT_RETURNTRANSFER, true ) ||
    !curl_setopt( $curl, CURLOPT_POST, true ) ||
    !curl_setopt( $curl, CURLOPT_POSTFIELDS, json_encode( $req ) ) ||
    !curl_setopt( $curl, CURLOPT_HTTPHEADER, [ 'Authorization: Bearer ' . $access_token, 'Content-Type: application/json' ] ) ) {
  edie( 'Internal error (curl_setopt() failed)' );
}

$response = curl_exec( $curl );
if( !$response ) {
  edie( 'Webhook creation request failed: ' . curl_error( $curl ) );
}

$response_code = curl_getinfo( $curl, CURLINFO_RESPONSE_CODE );
if( $response_code >= 400 ) {
  // Try to grab the error message out of the response
  $json = json_decode( $response );
  if( NULL === $json ) {
    edie( 'Webhook creation failed, and could not parse JSON response from failure' );
  }

  if( !property_exists( $json, 'message' ) || !strlen( trim( $json->message ) ) ) {
    edie( 'Webhook creation failed, and no error message was present in the response' );
  }

  edie( 'Webhook creation failed: ' . $json->message );
}

$json = json_decode( $response );
if( NULL === $json ) {
  edie( 'Webhook creation succeeded, but failed to decode JSON response from PayPal' );
}

$id = false;

if( property_exists( $json, 'id' ) && strlen( trim( $json->id ) ) ) {
  $id = $json->id;
}

curl_close( $curl );

// Now create the account lookup

$curl = curl_init( 'https://api.paypal.com/v1/notifications/webhooks-lookup' );
if( !$curl ) {
  if( $id ) {
    edie( 'Webhook creation succeeded, but curl_init() call failed when attempting to create account lookup (webhook ID: ' . $id . ')' );
  }
  edie( 'Webhook creation succeeded, but curl_init() call failed when attempting to create account lookup; additionally, webhook ID was missing from webhook creation response' );
}

if( !curl_setopt( $curl, CURLOPT_RETURNTRANSFER, true ) ||
    !curl_setopt( $curl, CURLOPT_POST, true ) ||
    !curl_setopt( $curl, CURLOPT_POSTFIELDS, '' ) ||
    !curl_setopt( $curl, CURLOPT_HTTPHEADER, [ 'Authorization: Bearer ' . $access_token, 'Content-Type: application/json' ] ) ) {
  if( $id ) {
    edie( 'Webhook creation succeeded, but curl_setopt() call failed when attempting to create account lookup (webhook ID: ' . $id . ')' );
  }
  edie( 'Webhook creation succeeded, but curl_setopt() call failed when attempting to create account lookup; additionally, webhook ID was missing from webhook creation response' );
}

$response = curl_exec( $curl );
if( !$response ) {
  if( $id ) {
    edie( 'Webhook creation succeeded, but account lookup creation request failed: ' . curl_error( $curl ) . ' (webhook ID: ' . $id . ')' );
  }
  edie( 'Webhook creation succeeded, but account lookup creation request failed: ' . curl_error( $curl ) . '; additionally, webhook ID was missing from webhook creation response' );
}

$response_code = curl_getinfo( $curl, CURLINFO_RESPONSE_CODE );
if( $response_code >= 400 ) {
  // Try to parse the message out of the response
  $json = json_decode( $response );
  if( $json === NULL ) {
    if( $id ) {
      edie( 'Webhook creation succeeded, but account lookup creation failed and could not parse JSON response from failure (webhook ID: ' . $id . ')' );
    }
    edie( 'Webhook creation succeeded, but account lookup creation failed and could not parse JSON response from failure; additionally, webhook ID was missing from webhook creation response' );
  }

  if( !property_exists( $json, 'message' ) || !strlen( trim( $json->message ) ) ) {
    if( $id ) {
      edie( 'Webhook creation succeeded, but account lookup creation failed and no error message was present in the failure response (webhook ID: ' . $id . ')' );
    }
    edie( 'Webhook creation succeeded, but account lookup creation failed and no error message was present in the failure response; additionally, webhook ID was missing from webhook creation response' );
  }

  if( $id ) {
    edie( 'Webhook creation succeeded, but account lookup creation failed: ' . $json->message . ' (webhook ID: ' . $id . ')' );
  }

  edie( 'Webhook creation succeeded, but account lookup creation failed: ' . $json->message . '; additionally, webhook ID was missing from webhook creation response' );
}

$response = [ 'ok' => true, 'id' => $id ];
die( json_encode( $response ) );
