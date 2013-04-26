<?php
/*
 * Leverages Github pushes via service hooks to a fogbugz Email account, placing the commit notifications in the enumerated ticket history.
 * As BugzID numbers are unique across projects, this does not require any configuration on the fogbugz instance.
 */
$fogBugzEmail='';
$replyToEmail='';
$logFile='';

try {
	$payload = json_decode($_REQUEST['payload']);
} catch(Exception $e) {
	exit(0);
}

preg_match( '/.*Bug[sz]id *: *(\d{1,5})/i', $payload->head_commit->message, $match );

if ( $match[1] > 0 ) {
	$mailSubject = '(Case ' . $match[1] . ') ' . $payload->head_commit->message;
	$mailMessage = "Commit Pushed\n----------\nAuthor : " . $payload->head_commit->author->email . "\nMessage : " . $payload->head_commit->message."\nURL : ".$payload->head_commit->url;
	$to      = $fogBugzEmail;
	$headers =		'From: ' . $replyToEmail  . "\r\n" .
					'Reply-To: ' . $replyToEmail . "\r\n" .
					'X-Mailer: PHP/' . phpversion();
	file_put_contents($logFile, "\n".$mailMessage."\n".$to."\n".$headers, FILE_APPEND);
	mail($to, $mailSubject, $mailMessage, $headers);
}
