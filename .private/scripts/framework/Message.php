<?php
/*! Message.php
 *
 *  Locale based message system.
 */

namespace framework;

class Message {

  public static function
  /* Boolean */ set($appId, $funcId, $msgId, $contents, $locale = '') {

    authorize(USR_ADMIN);

    return \Node::set(array(
        'APP_ID' => intval($appId)
      , 'FUNC_ID' => intval($funcId)
      , 'MSG_ID' => intval($msgId)
      , 'contents' => $contents
      , 'locale' => $locale
      ));
  }

  /**
   * Overloaded:
   * get( int $appId, int $funcId, int $msgId, [ string $locale ] );
   * get( string "$appId-$funcId-$msgId", [ string $locale ] );
   * get( $msgId, [ string $locale ] );
   */
  public static function
  /* String */ get() {
    $args = func_get_args();

    $filter = Array(
      NODE_FIELD_COLLECTION => FRAMEWORK_COLLECTION_MESSAGE
    );

    switch (count($args)) {
      case 2:
      case 4:
        $filter['locale'] = array_pop($args);
      default:
      	$filter['locale'] = Session::current('locale');
    }

    if (count($args) === 1) {
    	if (is_string($args[0])) {
	    	$args = explode('-', $args[0], 3);
    	}
    }

    list(
      $filter['APP_ID']
    , $filter['FUNC_ID']
    , $filter['MSG_ID']
    ) = $args;

    $res = \Node::get($filter);

    return @$res[0]['contents'];
  }

  /**
   * For development only.
   */
  public static function
  /* String */ getWithDefault($appId, $funcId, $msgId, $locale, $default) {
  	if (!self::get($appId, $funcId, $msgId, $locale)) {
  		self::set($appId, $funcId, $msgId, $default, $locale);
  	}

  	return self::get($appId, $funcId, $msgId, $locale);
  }

}