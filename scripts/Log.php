<?php

class Log
{
	/**
	 *  Write a system log with specified contents.
	 */
	static function write()
	{
		$argv = func_get_args();
		
		switch ( count($argv) ) {
			case 1:
				return self::writeRaw( $argv[0] );
				break;
			case 3:
				$argv[] = NULL;
			case 4:
			default:
				return self::writeFull( $argv[0], $argv[1], $argv[2], $argv[3] );
				break;
		}
	}
	
	static private function writeRaw( $str )
	{
		return Database::upsert( LOG_TABLENAME, 
		                         Array(
		                         	'ID' => NULL,
		                         	'SubjectID' => 0,
		                         	'identifier' => '::raw',
		                         	'action' => '',
		                         	'remarks' => $str
		                         ));
	}
	
	static private function writeFull( $sid, $identifier, $action, $remarks = NULL )
	{
		$sessionUser = NULL;
		
		if ( $sid !== NULL ) {
			$sessionUser = Session::getUser($sid);
		}
		
		if ( !$sessionUser ) {
			$sessionUser = Array('ID' => 0);
		}
		
		if ( $remarks === NULL ) {
			$remarks = Utility::sanitizeString($remarks);
		}
		
		return Database::upsert( LOG_TABLENAME, 
								 Array(
									'ID' => NULL,
									'SubjectID' => Utility::sanitizeInt($sessionUser['ID']),
									'identifier' => Utility::sanitizeString($identifier),
									'action' => Utility::sanitizeString($action),
									'remarks' => $remarks
								 ));
	}
}