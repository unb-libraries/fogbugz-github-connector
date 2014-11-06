<?php
/*
 * Leverages Github pushes via service hooks to fogbugz using the fogbugz api, placing the commit notifications in the enumerated ticket history and closing the case if specified.
 * As BugzID numbers are unique across projects, this does not require any configuration on the fogbugz instance.
 */

require 'config.inc.php';

try {
  $payload = json_decode($_REQUEST['payload']);
} catch(Exception $e) {
  exit(0);
}

foreach ($payload->commits as $current_commit) {
  $actions = array();
  $close_match = array();
  preg_match('/.*Closes{0,1} *Bug[sz]id *: *(\d{1,5})/i', $current_commit->message, $close_match);
  if (count($close_match) > 1 && $close_match[1] > 0) {
    $token = get_fogbugz_token($fogbugzUserEmail, $fogbugzPassword);
    $caseID = $close_match[1];
    $resolve_message = generate_message($current_commit);
    $resolve_success = fogbugz_api_call($token, 'resolve', $caseID, $resolve_message);
    $close_success = FALSE;
    if ($resolve_success) {
      $close_success = fogbugz_api_call($token, 'close', $caseID);
    }
    if ($resolve_success && $close_success) {
      file_put_contents($logFile, "\n".$resolve_message, FILE_APPEND);
    }
    else {
      file_put_contents($logFile, "\nCase resolve/close failed.", FILE_APPEND);
    }
  }
  else {
    $general_match = array();
    preg_match('/.*Bug[sz]id *: *(\d{1,5})/i', $current_commit->message, $general_match);
    if (count($general_match) > 1 && $general_match[1] > 0) {
      $token = get_fogbugz_token($fogbugzUserEmail, $fogbugzPassword);
      $caseID = $general_match[1];
      $message = generate_message($current_commit);
      $success = fogbugz_api_call($token, 'edit', $caseID, $message);
      if ($success) {
        file_put_contents($logFile, "\n".$message, FILE_APPEND);
      }
      else {
        file_put_contents($logFile, "\nCase update failed.", FILE_APPEND);
      }
    }
  }
}

/**
 * Generates a message from commit information.
 *
 * @param commit $commit
 *
 * @return string
 *   Message for fogbugz
 */
function generate_message($commit) {
  return "Commit Pushed\n----------\nAuthor : " . $commit->author->email . "\nMessage : " . $commit->message."\nURL : ".$commit->url;
}

/**
 * Authenticates Mr. Robot through the FogBugz API and generates a token for subsequent API calls.
 *
 * @param string $fogbugzUserEmail
 *   Email of fogbugz user used for API calls
 * @param string $fogbugzPassword
 *   Password of fogbugz user used for API calls
 *
 * @return string
 *   Token for FogBugz API calls
 */
function get_fogbugz_token($fogbugzUserEmail, $fogbugzPassword) {
  $curl_handle = curl_init('http://support.lib.unb.ca/api.asp?cmd=logon');

  curl_setopt($curl_handle, CURLOPT_RETURNTRANSFER, TRUE);
  curl_setopt($curl_handle, CURLOPT_POST, TRUE);
  $ch_post_data = array(
    'email' => $fogbugzUserEmail,
    'password' => $fogbugzPassword,
  );
  curl_setopt($curl_handle, CURLOPT_POSTFIELDS, $ch_post_data);

  $curl_response = curl_exec($curl_handle);
  $token = NULL;
  if ($curl_response) {
    $xml = simplexml_load_string($curl_response, 'SimpleXMLElement', LIBXML_NOCDATA);
    $token = (string) $xml->token;
  }
  curl_close($curl_handle);

  return $token;
}

/**
 * Performs a FogBugz API call
 *
 * @param string $token
 *   Token for FogBugz API calls
 * @param string $action
 *   API action (edit, resolve, etc)
 * @param int $caseID
 *   ID of FogBugz case
 * @param string $message
 *   Message to set in fogbugz
 *
 * @return bool
 *   TRUE if action completed successfully, FALSE if not
 */
function fogbugz_api_call($token, $action, $caseID, $message='') {
  $curl_handle = curl_init('http://support.lib.unb.ca/api.asp?cmd=' . $action);
  curl_setopt($curl_handle, CURLOPT_RETURNTRANSFER, TRUE);
  curl_setopt($curl_handle, CURLOPT_POST, TRUE);
  $ch_post_data = array(
    'token'  => $token,
    'ixBug'  => $caseID,
    'sEvent' => $message,
  );
  curl_setopt($curl_handle, CURLOPT_POSTFIELDS, $ch_post_data);

  $curl_response = curl_exec($curl_handle);
  curl_close($curl_handle);

  $case_id = NULL;
  if ($curl_response) {
    $xml = simplexml_load_string($curl_response, 'SimpleXMLElement', LIBXML_NOCDATA);
    $case_id = (string) $xml->case['ixBug'];
    if ($case_id != NULL) {
      return TRUE;
    }
  }
  return FALSE;
}
