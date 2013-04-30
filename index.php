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

foreach ($payload->commits as $current_commit) {
	$match=array();
	preg_match( '/.*Bug[sz]id *: *(\d{1,5})/i', $current_commit->message, $match );
	if ( $match[1] > 0 ) {
		$mailSubject = '(Case ' . $match[1] . ') ' . $current_commit->message;
		$mailMessage = "Commit Pushed\n----------\nAuthor : " . $current_commit->author->email . "\nMessage : " . $current_commit->message."\nURL : ".$current_commit->url;
		$to      = $fogBugzEmail;
		$headers =		'From: ' . $replyToEmail  . "\r\n" .
						'Reply-To: ' . $replyToEmail . "\r\n" .
						'X-Mailer: PHP/' . phpversion();
		file_put_contents($logFile, "\n".$mailMessage."\n".$to."\n".$headers, FILE_APPEND);
		mail($to, $mailSubject, $mailMessage, $headers);
	}
}
