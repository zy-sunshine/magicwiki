<?php

class SpecialAPC extends SpecialPage {
	const GRAPH_SIZE = 200;

	// Stored objects

	/**
	 * @var FormOptions
	 */
	protected $opts;

	/**
	 * @var Title
	 */
	protected $title;

	function __construct() {
		parent::__construct( 'APC' );
		$this->title = $this->getTitle();
	}

	const MODE_STATS = 0;
	const MODE_SYSTEM_CACHE = 1;
	const MODE_USER_CACHE = 2;
	const MODE_SYSTEM_CACHE_DIR = 3;
	const MODE_VERSION_CHECK = 4;

	public function setup() {
		$opts = new FormOptions();
		// Bind to the member variable
		$this->opts = $opts;

		$opts->add( 'mode', self::MODE_STATS );
		$opts->add( 'image', APCImages::IMG_NONE );
		$opts->add( 'clearcache', false );
		$opts->add( 'limit', 20 );
		$opts->add( 'offset', 0 );
		$opts->add( 'display', '' );
		$opts->add( 'delete', '' );
		$opts->add( 'sort', 'hits' );
		$opts->add( 'sortdir', 0 );
		$opts->add( 'scope', 'active' );
		$opts->add( 'searchi', '' ); // MediaWiki captures search, ARGH!

		$opts->fetchValuesFromRequest( $this->getRequest() );
		$opts->validateIntBounds( 'limit', 0, 5000 );
		$opts->validateIntBounds( 'sortdir', 0, 1 );
		$this->opts->consumeValues( array( 'display', 'clearcache', 'image' ) );

	}

	public function execute( $parameters ) {
		$this->setHeaders();
		$this->setup();

		$out = $this->getOutput();
		$user = $this->getUser();

		if ( !function_exists( 'apc_cache_info' ) ) {
			$out->addWikiMsg( 'viewapc-apc-not-available' );
			return;
		}

		if ( $this->opts->getValue( 'image' ) ) {
			$out->disable();
			header( 'Content-type: image/png' );
			echo APCImages::generateImage( $this->opts->getValue( 'image' ), $this->getContext() );
			return;
		}

		if ( $this->opts->getValue( 'mode' ) !== self::MODE_STATS ) {
			if ( !$user->isAllowed( 'apc' ) ) {
				throw new PermissionsError( 'apc' );
			}
		}

		// clear cache
		if ( $this->opts->getValue( 'clearcache' ) ) {
			$this->opts->setValue( 'clearcache', '' ); // TODO: reset
			if ( !$user->isAllowed( 'apc' ) ) {
				throw new PermissionsError( 'apc' );
			}
			$usermode = $this->opts->getValue( 'mode' ) === self::MODE_USER_CACHE;
			$mode = $usermode ? 'user' : 'opcode';
			apc_clear_cache( $mode );
			if ( $usermode ) {
				$out->addWikiMsg( 'viewapc-filecache-cleared' );
			} else {
				$out->addWikiMsg( 'viewapc-usercache-cleared' );
			}
		}

		$delete = $this->opts->getValue( 'delete' );
		if ( $delete ) {
			$this->opts->setValue( 'delete', '' ); // TODO: reset
			if ( !$user->isAllowed( 'apc' ) ) {
				throw new PermissionsError( 'apc' );
			}
			$result = apc_delete( $delete );
			if ( $result ) {
				$out->addWikiMsg( 'viewapc-delete-ok', $delete );
			} else {
				$out->addWikiMsg( 'viewapc-delete-failed', $delete );
			}
		}

		$out->addModuleStyles( 'ext.apc' );

		$this->getLogo();
		$this->mainMenu();
		$this->doPage();
	}

	protected function selfLink( $parms, $name, $attribs = array() ) {
		$title = $this->getTitle();
		$target = $title->getLocalURL( $parms );
		return Xml::element( 'a', array( 'href' => $target ) + $attribs, $name );
	}

	protected function getSelfURL( $overrides ) {
		$changed = $this->opts->getChangedValues();
		$target = $this->title->getLocalURL( wfArrayToCgi( $overrides, $changed ) );
		return $target;
	}

	protected function selfLink2( $title, $overrides ) {
		$changed = $this->opts->getChangedValues();
		$target = $this->title->getLocalURL( wfArrayToCgi( $overrides, $changed ) );
		return Xml::tags( 'a', array( 'href' => $target ), $title );
	}

	protected function menuItem( $mode, $text ) {
		$params = array( 'mode' => $mode );
		return Xml::tags( 'li', null, $this->selfLink2( $text, $params ) );
	}

	const APCURL = 'http://pecl.php.net/package/APC';

	protected function getLogo() {
		$logo =
			Xml::wrapClass( Xml::element( 'a', array( 'href' => self::APCURL ), 'APC' ), 'mw-apc-logo' ) .
				Xml::wrapClass( 'Opcode Cache', 'mw-apc-nameinfo' );

		$this->getOutput()->addHTML(
			Xml::openElement( 'div', array( 'class' => 'head' ) ) .
				Xml::tags( 'h1', array( 'class' => 'apc-header-1' ),
					Xml::wrapClass( $logo, 'mw-apc-logo-outer', 'span' )
				) .

				Xml::element( 'hr', array( 'class' => 'mw-apc-separator' ) ) .
				Xml::closeElement( 'div' )
		);
	}

	protected function mainMenu() {
		if ( !$this->getUser()->isAllowed( 'apc' ) ) {
			return;
		}

		$clearParams = array(
			'clearcache' => 1,
		);
		$clearText = $this->opts->getValue( 'mode' ) === self::MODE_USER_CACHE ?
			$this->msg( 'viewapc-clear-user-cache' )->escaped() :
			$this->msg( 'viewapc-clear-code-cache' )->escaped();

		$this->getOutput()->addHTML(
			Xml::openElement( 'ol', array( 'class' => 'mw-apc-menu' ) ) .
				$this->menuItem( self::MODE_STATS, $this->msg( 'viewapc-mode-stats' )->escaped() ) .
				$this->menuItem( self::MODE_SYSTEM_CACHE, $this->msg( 'viewapc-mode-system-cache' )->escaped() ) .
				$this->menuItem( self::MODE_USER_CACHE, $this->msg( 'viewapc-mode-user-cache' )->escaped() ) .
				$this->menuItem( self::MODE_VERSION_CHECK, $this->msg( 'viewapc-mode-version-check' )->escaped() ) .
				Xml::tags( 'li', null,
					$this->selfLink2( $clearText, $clearParams ) ) .
				Xml::closeElement( 'ol' )
		);
	}

	protected function doObHostStats() {
		$mem = apc_sma_info();

		$clear = Xml::element( 'br', array( 'style' => 'clear: both;' ) );

		$usermode = $this->opts->getValue( 'mode' ) === self::MODE_USER_CACHE;
		$cache = apc_cache_info( $usermode ? 'user' : 'opcode' );

		$this->getOutput()->addHTML(
			APCHostMode::doGeneralInfoTable( $cache, $mem, $this->getContext() ) .
				APCHostMode::doMemoryInfoTable( $cache, $mem, $this->title, $this->getContext() ) . $clear .
				APCHostMode::doCacheTable( $cache, false, $this->getContext() ) .
				APCHostMode::doCacheTable( apc_cache_info( 'user', 1 ), true, $this->getContext() ) . $clear .
				APCHostMode::doRuntimeInfoTable( $this->getContext() ) .
				APCHostMode::doFragmentationTable( $mem, $this->title, $this->getContext() ) . $clear
		);
	}

	protected function doPage() {
		$this->getOutput()->addHTML(
			Xml::openElement( 'div', array( 'class' => 'mw-apc-content' ) )
		);

		switch ( $this->opts->getValue( 'mode' ) ) {
			case self::MODE_STATS:
				$this->doObHostStats();
				break;
			case self::MODE_SYSTEM_CACHE:
			case self::MODE_USER_CACHE:
				$mode = new APCCacheMode( $this->opts, $this->title, $this->getContext() );
				$mode->cacheView();
				break;
			case self::MODE_VERSION_CHECK:
				$this->versionCheck();
				break;
		}

		$this->getOutput()->addHTML(
			Xml::closeElement( 'div' )
		);
	}

	protected function versionCheck() {
		$out = $this->getOutput();

		$out->addHTML(
			Xml::element( 'h2', null, $this->msg( 'viewapc-version-info' )->text() )
		);

		$rss = Http::get( 'http://pecl.php.net/feeds/pkg_apc.rss' );
		if ( !$rss ) {
			$out->addWikiMsg( 'viewapc-version-failed' );
		} else {
			$apcversion = phpversion( 'apc' );

			preg_match( '!<title>APC ([0-9.]+)</title>!', $rss, $match );
			if ( version_compare( $apcversion, $match[1], '>=' ) ) {
				$out->addWikiMsg( 'viewapc-version-ok', $apcversion );
				$i = 3;
			} else {
				$out->addWikiMsg( 'viewapc-version-old', $apcversion, $match[1] );
				$i = -1;
			}

			$out->addHTML(
				Xml::element( 'h3', null, $this->msg( 'viewapc-version-changelog' )->text() )
			);


			preg_match_all( '!<(title|description)>([^<]+)</\\1>!', $rss, $match );
			next( $match[2] );
			next( $match[2] );

			while ( list( , $v ) = each( $match[2] ) ) {
				list( , $ver ) = explode( ' ', $v, 2 );
				if ( $i < 0 && version_compare( $apcversion, $ver, '>=' ) ) {
					break;
				} elseif ( !$i-- ) {
					break;
				}
				$data = current( $match[2] );
				$out->addWikiText( "''[http://pecl.php.net/package/APC/$ver $v]''<br /><pre>$data</pre>" );
				next( $match[2] );
			}
		}
	}
}
