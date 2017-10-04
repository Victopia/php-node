<?php /* CorsResolver.php | Cross-Origin Resource Sharing header handling. */

namespace resolvers;

use framework\Request;
use framework\Response;

use framework\exceptions\ResolverException;

class CorsResolver implements \framework\interfaces\IRequestResolver {

  /**
   * @protected
   *
   * Patterns to be matched against requesting domains.
   */
  protected $patterns = [];

  /**
   * @protected
   *
   * Array or comma-separated string of Access-Control-Allow-Headers.
   */
  protected $headers = [];

  /**
   * @constructor
   *
   * @param {array}        $options[patterns] Domains to be allowed, regex patterns are allowed.
   * @param {array|string} $options[headers]  Array or comma-separated string of Access-Control-Allow-Headers.
   */
  public function __construct(array $options = array()) {
    if ( !empty(@$options['patterns']) ) {
      $this->patterns = mapdef((array) $options['patterns'], 'trim');
    }

    if ( !empty($options['headers']) ) {
      if ( is_array($options['headers']) ) {
        $this->headers = implode(',', $options['headers']);
      }
      else {
        $this->headers = @"$options[headers]";
      }
    }
  }

  //--------------------------------------------------
  //
  //  Methods: IPathResolver
  //
  //--------------------------------------------------

  /**
   * Response with appropriate headers and an empty body.
   */
  public function resolve(Request $req, Response $res) {
    /*! note;dev; Proper CORS handling
        @see https://stackoverflow.com/questions/1653308/access-control-allow-origin-multiple-origin-domains

        According to the link above, servers should not give hints about which
        domain it actually allows. The domain matching with Origin header
        should happens on server-side, responding with, and only with, the
        originating domain itself when it matches.
     */

    $origin = $req->client('origin');
    if ( !$origin ) {
      $origin = $req->client('referer');
    }

    if ( $origin ) {
      foreach ( $this->patterns as $pattern ) {
        if ( (@preg_match($pattern, null) !== false && preg_match($pattern, $origin)) || $pattern == $origin || $pattern == '*' ) {
          $res->header('Access-Control-Allow-Origin', $origin);
          $res->header('Access-Control-Allow-Headers', $this->headers);

          break;
        }
      }
    }

    if ( $req->method() == 'options' ) {
      throw new ResolverException(200);
    }
  }

}
