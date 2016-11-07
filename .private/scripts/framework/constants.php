<?php
/* constants.php | Define framework wide constants. */

// Seconds between real Epoch and 1970-01-01, GMT.
define('EPOACH', -62167219200); // -62167246602

// Shorthand constants
define('DS', DIRECTORY_SEPARATOR);

// Added for frequent use, this is too common to delete.
define('NODE_FIELD_COLLECTION', core\Node::FIELD_COLLECTION);

// Collection of system configurations
define('FRAMEWORK_COLLECTION_CONFIGURATION', 'Configurations');
// Collection of Node hirarchy relations
define('FRAMEWORK_COLLECTION_RELATION', 'NodeRelations');
// Collection of system logs
define('FRAMEWORK_COLLECTION_LOG', 'Logs');
// Collection of processes
define('FRAMEWORK_COLLECTION_PROCESS', 'Processes');
// Collection of users
define('FRAMEWORK_COLLECTION_USER', 'Users');
// Collection of http user sessions
define('FRAMEWORK_COLLECTION_SESSION', 'Sessions');
// Collection of locale resources.
define('FRAMEWORK_COLLECTION_TRANSLATION', 'Translations');

// Predefined error messages in libcurl.
define('FRAMEWORK_NET_CURL_ERRORS', serialize(array(
    CURLE_OK => 'All fine. Proceed as usual.'
  , CURLE_UNSUPPORTED_PROTOCOL => 'The URL you passed to libcurl used a protocol that this libcurl does not support. The support might be a compile-time option that you didn\'t use, it can be a misspelled protocol string or just a protocol libcurl has no code for.'
  , CURLE_FAILED_INIT => 'Very early initialization code failed. This is likely to be an internal error or problem, or a resource problem where something fundamental couldn\'t get done at init time.'
  , CURLE_URL_MALFORMAT => 'The URL was not properly formatted.'
//  , CURLE_NOT_BUILT_IN => 'A requested feature, protocol or option was not found built-in in this libcurl due to a build-time decision. This means that a feature or option was not enabled or explicitly disabled when libcurl was built and in order to get it to function you have to get a rebuilt libcurl.'
  , CURLE_COULDNT_RESOLVE_PROXY => 'Couldn\'t resolve proxy. The given proxy host could not be resolved.'
  , CURLE_COULDNT_RESOLVE_HOST => 'Couldn resolve host. The given remote host was not resolved.'
  , CURLE_COULDNT_CONNECT => 'Failed to connect() to host or proxy.'
  , CURLE_FTP_WEIRD_SERVER_REPLY => 'After connecting to a FTP server, libcurl expects to get a certain reply back. This error code implies that it got a strange or bad reply. The given remote server is probably not an OK FTP server.'
//  , CURLE_REMOTE_ACCESS_DENIED => 'We were denied access to the resource given in the URL. For FTP, this occurs while trying to change to the remote directory.'
//  , CURLE_FTP_ACCEPT_FAILED => 'While waiting for the server to connect back when an active FTP session is used, an error code was sent over the control connection or similar.'
  , CURLE_FTP_WEIRD_PASS_REPLY => 'After having sent the FTP password to the server, libcurl expects a proper reply. This error code indicates that an unexpected code was returned.'
//  , CURLE_FTP_ACCEPT_TIMEOUT => 'During an active FTP session while waiting for the server to connect, the CURLOPT_ACCEPTTIMOUT_MS (or the internal default) timeout expired.'
  , CURLE_FTP_WEIRD_PASV_REPLY => 'libcurl failed to get a sensible result back from the server as a response to either a PASV or a EPSV command. The server is flawed.'
  , CURLE_FTP_WEIRD_227_FORMAT => 'FTP servers return a 227-line as a response to a PASV command. If libcurl fails to parse that line, this return code is passed back.'
  , CURLE_FTP_CANT_GET_HOST => 'An internal failure to lookup the host used for the new connection.'
//  , CURLE_FTP_COULDNT_SET_TYPE => 'Received an error when trying to set the transfer mode to binary or ASCII.'
  , CURLE_PARTIAL_FILE => 'A file transfer was shorter or larger than expected. This happens when the server first reports an expected transfer size, and then delivers data that doesn match the previously given size.'
  , CURLE_FTP_COULDNT_RETR_FILE => 'This was either a weird reply to a \'RETR\' command or a zero byte transfer complete.'
//  , CURLE_QUOTE_ERROR => 'When sending custom "QUOTE" commands to the remote server, one of the commands returned an error code that was 400 or higher (for FTP) or otherwise indicated unsuccessful completion of the command.'
//  , CURLE_HTTP_RETURNED_ERROR => 'This is returned if CURLOPT_FAILONERROR is set TRUE and the HTTP server returns an error code that is >= 400.'
  , CURLE_WRITE_ERROR => 'An error occurred when writing received data to a local file, or an error was returned to libcurl from a write callback.'
//  , CURLE_UPLOAD_FAILED => 'Failed starting the upload. For FTP, the server typically denied the STOR command. The error buffer usually contains the server\'s explanation for this.'
  , CURLE_READ_ERROR => 'There was a problem reading a local file or an error returned by the read callback.'
  , CURLE_OUT_OF_MEMORY => 'A memory allocation request failed. This is serious badness and things are severely screwed up if this ever occurs.'
//  , CURLE_OPERATION_TIMEDOUT => 'Operation timeout. The specified time-out period was reached according to the conditions.'
  , CURLE_FTP_PORT_FAILED => 'The FTP PORT command returned error. This mostly happens when you haven\'t specified a good enough address for libcurl to use. See CURLOPT_FTPPORT.'
  , CURLE_FTP_COULDNT_USE_REST => 'The FTP REST command returned error. This should never happen if the server is sane.'
//  , CURLE_RANGE_ERROR => 'The server does not support or accept range requests.'
  , CURLE_HTTP_POST_ERROR => 'This is an odd error that mainly occurs due to internal confusion.'
  , CURLE_SSL_CONNECT_ERROR => 'A problem occurred somewhere in the SSL/TLS handshake. You really want the error buffer and read the message there as it pinpoints the problem slightly more. Could be certificates (file formats, paths, permissions), passwords, and others.'
//  , CURLE_BAD_DOWNLOAD_RESUME => 'The download could not be resumed because the specified offset was out of the file boundary.'
  , CURLE_FILE_COULDNT_READ_FILE => 'A file given with FILE:// couldn\'t be opened. Most likely because the file path doesn\'t identify an existing file. Did you check file permissions?'
  , CURLE_LDAP_CANNOT_BIND => 'LDAP cannot bind. LDAP bind operation failed.'
  , CURLE_LDAP_SEARCH_FAILED => 'LDAP search failed.'
  , CURLE_FUNCTION_NOT_FOUND => 'Function not found. A required zlib function was not found.'
  , CURLE_ABORTED_BY_CALLBACK => 'Aborted by callback. A callback returned "abort" to libcurl.'
  , CURLE_BAD_FUNCTION_ARGUMENT => 'Internal error. A function was called with a bad parameter.'
//  , CURLE_INTERFACE_FAILED => 'Interface error. A specified outgoing interface could not be used. Set which interface to use for outgoing connections\' source IP address with CURLOPT_INTERFACE.'
  , CURLE_TOO_MANY_REDIRECTS => 'Too many redirects. When following redirects, libcurl hit the maximum amount. Set your limit with CURLOPT_MAXREDIRS.'
//  , CURLE_UNKNOWN_OPTION => 'An option passed to libcurl is not recognized/known. Refer to the appropriate documentation. This is most likely a problem in the program that uses libcurl. The error buffer might contain more specific information about which exact option it concerns.'
  , CURLE_TELNET_OPTION_SYNTAX => 'A telnet option string was Illegally formatted.'
//  , CURLE_PEER_FAILED_VERIFICATION => 'The remote server\'s SSL certificate or SSH md5 fingerprint was deemed not OK.'
  , CURLE_GOT_NOTHING => 'Nothing was returned from the server, and under the circumstances, getting nothing is considered an error.'
  , CURLE_SSL_ENGINE_NOTFOUND => 'The specified crypto engine wasn\'t found.'
  , CURLE_SSL_ENGINE_SETFAILED => 'Failed setting the selected SSL crypto engine as default!'
  , CURLE_SEND_ERROR => 'Failed sending network data.'
  , CURLE_RECV_ERROR => 'Failure with receiving network data.'
  , CURLE_SSL_CERTPROBLEM => 'problem with the local client certificate.'
  , CURLE_SSL_CIPHER => 'couldn\'t use specified cipher.'
  , CURLE_SSL_CACERT => 'Peer certificate cannot be authenticated with known CA certificates.'
  , CURLE_BAD_CONTENT_ENCODING => 'Unrecognized transfer encoding.'
  , CURLE_LDAP_INVALID_URL => 'Invalid LDAP URL.'
  , CURLE_FILESIZE_EXCEEDED => 'Maximum file size exceeded.'
//  , CURLE_USE_SSL_FAILED => 'Requested FTP SSL level failed.'
//  , CURLE_SEND_FAIL_REWIND => 'When doing a send operation curl had to rewind the data to retransmit, but the rewinding operation failed.'
//  , CURLE_SSL_ENGINE_INITFAILED => 'Initiating the SSL Engine failed.'
//  , CURLE_LOGIN_DENIED => 'The remote server denied curl to login (Added in 7.13.1)'
//  , CURLE_TFTP_NOTFOUND => 'File not found on TFTP server.'
//  , CURLE_TFTP_PERM => 'Permission problem on TFTP server.'
//  , CURLE_REMOTE_DISK_FULL => 'Out of disk space on the server.'
//  , CURLE_TFTP_ILLEGAL => 'Illegal TFTP operation.'
//  , CURLE_TFTP_UNKNOWNID => 'Unknown TFTP transfer ID.'
//  , CURLE_REMOTE_FILE_EXISTS => 'File already exists and will not be overwritten.'
//  , CURLE_TFTP_NOSUCHUSER => 'This error should never be returned by a properly functioning TFTP server.'
//  , CURLE_CONV_FAILED => 'Character conversion failed.'
//  , CURLE_CONV_REQD => 'Caller must register conversion callbacks.'
//  , CURLE_SSL_CACERT_BADFILE => 'Problem with reading the SSL CA cert (path? access rights?)'
//  , CURLE_REMOTE_FILE_NOT_FOUND => 'The resource referenced in the URL does not exist.'
  , CURLE_SSH => 'An unspecified error occurred during the SSH session.'
//  , CURLE_SSL_SHUTDOWN_FAILED => 'Failed to shut down the SSL connection.'
//  , CURLE_AGAIN => 'Socket is not ready for send/recv wait till it\'s ready and try again. This return code is only returned from curl_easy_recv(3) and curl_easy_send(3) (Added in 7.18.2)'
//  , CURLE_SSL_CRL_BADFILE => 'Failed to load CRL file (Added in 7.19.0)'
//  , CURLE_SSL_ISSUER_ERROR => 'Issuer check failed (Added in 7.19.0)'
//  , CURLE_FTP_PRET_FAILED => 'The FTP server does not understand the PRET command at all or does not support the given argument. Be careful when using CURLOPT_CUSTOMREQUEST, a custom LIST command will be sent with PRET CMD before PASV as well. (Added in 7.20.0)'
//  , CURLE_RTSP_CSEQ_ERROR => 'Mismatch of RTSP CSeq numbers.'
//  , CURLE_RTSP_SESSION_ERROR => 'Mismatch of RTSP Session Identifiers.'
//  , CURLE_FTP_BAD_FILE_LIST => 'Unable to parse FTP file list (during FTP wildcard downloading).'
//  , CURLE_CHUNK_FAILED => 'Chunk callback reported error.'
  )));

// For how long a cookie should be stored.
define('FRAMEWORK_COOKIE_EXPIRE_TIME', strtotime('+ 1 week'));

// How long a session is considered valid.
define('FRAMEWORK_SESSION_EXPIRE_TIME', strtotime('+ 30 minute'));

// Date format for framework outputs
define('FRAMEWORK_DATE_FORMAT', 'd M, H:i');
