<?php

class APCCacheMode {
	protected $opts, $title;
	/**
	 * @var IContextSource
	 */
	protected $context;
	protected $userMode = false;
	protected $fieldKey;

	public function __construct( FormOptions $opts, Title $title, IContextSource $context ) {
		$this->opts = $opts;
		$this->title = $title;
		$this->context = $context;
		$this->userMode = $opts->getValue( 'mode' ) === SpecialAPC::MODE_USER_CACHE;
		$this->fieldKey = $this->userMode ? 'info' : ( ini_get( 'apc.stat' ) ? 'inode' : 'filename' );
	}

	protected $scopes = array(
		'A' => 'cache_list',
		'D' => 'deleted_list'
	);

	protected function displayObject( $object ) {
		$cache = apc_cache_info( $this->userMode ? 'user' : 'opcode' );

		$s =
			Xml::openElement( 'div', array( 'class' => 'mw-apc-listing' ) ) .
				Xml::openElement( 'table' ) . Xml::openElement( 'tbody' ) .
				Xml::openElement( 'tr' ) .
				Xml::element( 'th', null, $this->context->msg( 'viewapc-display-attribute' )->text() ) .
				Xml::element( 'th', null, $this->context->msg( 'viewapc-display-value' )->text() ) .
				Xml::closeElement( 'tr' );

		$r = 1;
		foreach ( $this->scopes as $list ) {
			foreach ( $cache[$list] as $entry ) {
				if ( md5( $entry[$this->fieldKey] ) !== $object ) {
					continue;
				}

				$size = 0;
				foreach ( $entry as $key => $value ) {
					switch ( $key ) {
						case 'num_hits':
							$value = $this->context->getLanguage()->formatNum( $value ) .
								$this->context->getLanguage()->formatNum( sprintf( " (%.2f%%)", $value * 100 / $cache['num_hits'] ) );
							break;
						case 'deletion_time':
							$value = $this->formatValue( $key, $value );
							if ( !$value ) {
								$value = $this->context->msg( 'viewapc-display-no-delete' )->text();
								break;
							}
						// @todo FIXME: Accidental fall through or on purpose?
						case 'mem_size':
							$size = $value;
						// @todo FIXME: Accidental fall through or on purpose?
						default:
							$value = $this->formatValue( $key, $value );
					}

					// Give grep a chance to find the usages:
					// viewapc-display-filename, viewapc-display-device, viewapc-display-info,
					// viewapc-display-ttl, viewapc-display-inode, viewapc-display-type,
					// viewapc-display-num_hits, viewapc-display-mtime, viewapc-display-creation_time,
					// viewapc-display-deletion_time, viewapc-display-access_time, viewapc-display-ref_count
					// viewapc-display-mem_size
					$s .= APCUtils::tableRow( $r = 1 - $r,
						$this->context->msg( 'viewapc-display-' . $key )->escaped(),
						htmlspecialchars( $value ) );

				}

				if ( $this->userMode ) {
					if ( $size > 1024 * 1024 ) {
						$s .= APCUtils::tableRow( $r = 1 - $r,
							$this->context->msg( 'viewapc-display-stored-value' )->escaped(),
							$this->context->msg( 'viewapc-display-too-big' )->parse() );
					} else {
						$value = var_export( apc_fetch( $entry[$this->fieldKey] ), true );
						$s .= APCUtils::tableRow( $r = 1 - $r,
							$this->context->msg( 'viewapc-display-stored-value' )->escaped(),
							Xml::element( 'pre', null, $value ) );
					}
				}
			}
		}

		$s .= '</tbody></table></div>';
		return $s;
	}

	// sortable table header in "scripts for this host" view
	protected function sortHeader( $title, $overrides ) {
		$changed = $this->opts->getChangedValues();
		$target = $this->title->getLocalURL( wfArrayToCgi( $overrides, $changed ) );
		return Xml::tags( 'a', array( 'href' => $target ), $title );
	}

	protected function formatValue( $type, $value ) {
		$lang = $this->context->getLanguage();

		switch ( $type ) {
			case 'deletion_time':
				if ( !$value ) {
					$value = false;
					break;
				}
			// @todo FIXME: Accidental fall through or on purpose?
			case 'mtime':
			case 'creation_time':
			case 'access_time':
				$value = $lang->timeanddate( $value );
				break;
			case 'ref_count':
			case 'num_hits':
				$value = $lang->formatNum( $value );
				break;
			case 'mem_size':
				$value = $lang->formatSize( $value );
				break;
			case 'ttl':
				$value = $lang->formatTimePeriod( $value );
				break;
			case 'type':
				// Give grep a chance to find the usages:
				// viewapc-display-type-file, viewapc-display-type-user
				$value = $this->context->msg( 'viewapc-display-type-' . $value )->text();
				break;
		}
		return $value;
	}

	public function cacheView() {
		$lang = $this->context->getLanguage();
		$out = $this->context->getOutput();

		$object = $this->opts->getValue( 'display' );
		if ( $object ) {
			$out->addHTML( $this->displayObject( $object ) );
			return;
		}

		$out->addHTML( $this->options() );
		$out->addHTML( '<div><table><tbody><tr>' );

		$fields = array( 'name', 'hits', 'size', 'accessed', 'modified', 'created' );
		if ( $this->userMode ) {
			$fields[] = 'timeout';
		}
		$fields[] = 'deleted';

		$fieldKeys = array(
			'name' => $this->userMode ? 'info' : 'filename',
			'hits' => 'num_hits',
			'size' => 'mem_size',
			'accessed' => 'access_time',
			'modified' => 'mtime',
			'created' => 'creation_time',
			'timeout' => 'ttl',
			'deleted' => 'deletion_time',
		);

		$scope = $this->opts->getValue( 'scope' );
		$sort = $this->opts->getValue( 'sort' );
		$sortdir = $this->opts->getValue( 'sortdir' );
		$limit = $this->opts->getValue( 'limit' );
		$offset = $this->opts->getValue( 'offset' );
		$search = $this->opts->getValue( 'searchi' );

		foreach ( $fields as $field ) {
			$extra = array();
			if ( $sort === $field ) {
				$extra = array( 'sortdir' => 1 - $sortdir );
			}

			// Give grep a chance to find the usages:
			// viewapc-ls-header-name, viewapc-ls-header-hits, viewapc-ls-header-size
			// viewapc-ls-header-accessed, viewapc-ls-header-modified
			// viewapc-ls-header-created, viewapc-ls-header-deleted
			// viewapc-ls-header-timeout
			$out->addHTML(
				Xml::tags( 'th', null, $this->sortHeader(
					$this->context->msg( 'viewapc-ls-header-' . $field )->escaped(),
					array( 'sort' => $field ) + $extra ) )
			);
		}

		$out->addHTML( '</tr>' );

		$cache = apc_cache_info( $this->userMode ? 'user' : 'opcode' );
		$list = array();
		if ( $scope === 'active' || $scope === 'both' ) {
			foreach ( $cache['cache_list'] as $entry ) {
				if ( $search && stripos( $entry[$fieldKeys['name']], $search ) === false ) {
					continue;
				}
				$sortValue = sprintf( '%015d-', $entry[$fieldKeys[$sort]] );
				$list[$sortValue . $entry[$fieldKeys['name']]] = $entry;
			}
		}

		if ( $scope === 'deleted' || $scope === 'both' ) {
			foreach ( $cache['deleted_list'] as $entry ) {
				if ( $search && stripos( $entry[$fieldKeys['name']], $search ) === false ) {
					continue;
				}
				$sortValue = sprintf( '%015d-', $entry[$fieldKeys[$sort]] );
				$list[$sortValue . $entry[$fieldKeys['name']]] = $entry;
			}
		}

		$sortdir ? krsort( $list ) : ksort( $list );

		$i = 0;
		if ( count( $list ) ) {
			$r = 1;

			foreach ( $list as $name => $entry ) {
				if ( $limit === $i++ ) {
					break;
				}
				$out->addHTML(
					Xml::openElement( 'tr', array( 'class' => 'mw-apc-tr-' . ( $r = 1 - $r ) ) )
				);

				foreach ( $fields as $field ) {
					$index = $fieldKeys[$field];
					if ( $field === 'name' ) {
						$value = '';
						if ( !$this->userMode ) {
							$pos = strrpos( $entry[$index], '/' );
							if ( $pos !== false ) {
								$value = substr( $entry[$index], $pos + 1 );
							}
						} else {
							$value = $entry[$index];
						}
						$value = $this->sortHeader( htmlspecialchars( $value ), array( 'display' => md5( $entry[$this->fieldKey] ) ) );
					} elseif ( $field === 'deleted' && $this->userMode && !$entry[$index] ) {
						$value = $this->sortHeader(
							$this->context->msg( 'viewapc-ls-delete' )->escaped(),
							array( 'delete' => $entry[$this->fieldKey] )
						);
					} else {
						$value = $this->formatValue( $index, $entry[$index] );
					}

					$out->addHTML( Xml::tags( 'td', null, $value ) );
				}

				$out->addHTML( '</tr>' );
			}
		}

		if ( $i < count( $list ) ) {
			$left = $lang->formatNum( count( $list ) - ( $i + $offset ) );
			$out->addHTML(
				Xml::tags( 'tr', array( 'colspan' => count( $fields ) ),
					Xml::tags( 'td', null, $this->sortHeader(
						$this->context->msg( 'viewapc-ls-more', $left )->parse(),
						array( 'offset' => $offset + $limit ) ) ) )
			);
		} elseif ( !count( $list ) ) {
			$out->addHTML(
				Xml::tags( 'tr', array( 'colspan' => count( $fields ) ),
					Xml::tags( 'td', null, $this->context->msg( 'viewapc-ls-nodata' )->parse() ) )
			);
		}

		$out->addHTML( '</tbody></table></div>' );
	}

	protected function options() {
		global $wgScript;

		$s =
			Xml::openElement( 'fieldset' ) .
				Xml::element( 'legend', null, $this->context->msg( 'viewapc-ls-options-legend' )->text() ) .
				Xml::openElement( 'form', array( 'action' => $wgScript ) );

		$s .= Html::Hidden( 'title', $this->title->getPrefixedText() );

		// Give grep a chance to find the usages:
		// viewapc-ls-scope-active, viewapc-ls-scope-deleted, viewapc-ls-scope-both
		$options = array();
		$scope = $this->opts->consumeValue( 'scope' );
		$scopeOptions = array( 'active', 'deleted', 'both' );
		foreach ( $scopeOptions as $name ) {
			$options[] = Xml::option( $this->context->msg( 'viewapc-ls-scope-' . $name )->text(), $name, $scope === $name );
		}
		$scopeSelector = Xml::tags( 'select', array( 'name' => 'scope' ), implode( "\n", $options ) );

		// Give grep a chance to find the usages:
		// viewapc-ls-sort-hits, viewapc-ls-sort-size, viewapc-ls-sort-name,
		// viewapc-ls-sort-accessed, viewapc-ls-sort-modified, viewapc-ls-sort-created,
		// viewapc-ls-sort-deleted, viewapc-ls-sort-timeout
		$options = array();
		$sort = $this->opts->consumeValue( 'sort' );
		$sortOptions = array( 'hits', 'size', 'name', 'accessed', 'modified', 'created', 'deleted' );
		if ( $this->userMode ) {
			$sortOptions[] = 'timeout';
		}
		foreach ( $sortOptions as $name ) {
			$options[] = Xml::option( $this->context->msg( 'viewapc-ls-sort-' . $name )->text(), $name, $sort === $name );
		}
		$sortSelector = Xml::tags( 'select', array( 'name' => 'sort' ), implode( "\n", $options ) );

		$options = array();
		// @todo FIXME: should be bool for 3rd para in Xml::option.
		$sortdir = $this->opts->consumeValue( 'sortdir' );
		$options[] = Xml::option( $this->context->msg( 'ascending_abbrev' )->text(), 0, !$sortdir );
		$options[] = Xml::option( $this->context->msg( 'descending_abbrev' )->text(), 1, $sortdir );
		$sortdirSelector = Xml::tags( 'select', array( 'name' => 'sortdir' ), implode( "\n", $options ) );

		$options = array();
		$limit = $this->opts->consumeValue( 'limit' );
		$limitOptions = array( 10, 20, 50, 150, 200, 500, $limit );
		sort( $limitOptions );
		$name = 0;
		foreach ( $limitOptions as $name ) {
			$options[] = Xml::option( $this->context->getLanguage()->formatNum( $name ), $name, $limit === $name );
		}
		$options[] = Xml::option( $this->context->msg( 'viewapc-ls-limit-none' )->text(), 0, $limit === $name );
		$limitSelector = Xml::tags( 'select', array( 'name' => 'limit' ), implode( "\n", $options ) );

		$searchBox = Xml::input( 'searchi', 25, $this->opts->consumeValue( 'searchi' ) );
		$submit = Xml::submitButton( $this->context->msg( 'viewapc-ls-submit' )->text() );

		foreach ( $this->opts->getUnconsumedValues() as $key => $value ) {
			$s .= Html::Hidden( $key, $value );
		}

		$s .= $this->context->msg( 'viewapc-ls-options' )->rawParams( $scopeSelector, $sortSelector,
			$sortdirSelector, $limitSelector, $searchBox, $submit )->escaped();
		$s .= '</form></fieldset><br />';

		return $s;
	}
}
