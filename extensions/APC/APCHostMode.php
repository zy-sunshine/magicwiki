<?php

class APCHostMode {
	public static function doGeneralInfoTable( $c, $mem, IContextSource $context ) {
		$lang = $context->getLanguage();
		$r = 1;

		return
			APCUtils::tableHeader( $context->msg( 'viewapc-info-general' )->text() ) .
			APCUtils::tableRow( $r = 1 - $r, $context->msg( 'viewapc-apc-version' )->escaped(), phpversion( 'apc' ) ) .
			APCUtils::tableRow( $r = 1 - $r, $context->msg( 'viewapc-php-version' )->escaped(), phpversion() ) .

			APCUtils::tableRow( $r = 1 - $r, $context->msg( 'viewapc-shared-memory' )->escaped(),
				$context->msg( 'viewapc-shared-memory-details' )->numParams( $mem['num_seg'] )->
					params( $lang->formatSize( $mem['seg_size'] ),
					$c['memory_type'], $c['locking_type'] )->text() ) .

			APCUtils::tableRow( $r = 1 - $r, $context->msg( 'viewapc-start-time' )->escaped(),
				$lang->timeanddate( $c['start_time'] ) ) .

			APCUtils::tableRow( $r = 1 - $r, $context->msg( 'viewapc-uptime' )->escaped(),
				$lang->formatTimePeriod( time() - $c['start_time'] ) ) .

			APCUtils::tableRow( $r = 1 - $r, $context->msg( 'viewapc-upload-support' )->escaped(), $c['file_upload_progress'] ) .
			APCUtils::tableFooter();
	}

	public static function doCacheTable( $c, $user = false, IContextSource $context ) {
		$lang = $context->getLanguage();

		// Calculate rates
		$numHits = $lang->formatNum( $c['num_hits'] );
		$numMiss = $lang->formatNum( $c['num_misses'] );
		$numReqs = $c['num_hits'] + $c['num_misses'];
		$cPeriod = time() - $c['start_time'];
		if ( !$cPeriod ) {
			$cPeriod = 1;
		}
		$rateReqs = APCUtils::formatReqPerS( $numReqs / $cPeriod );
		$rateHits = APCUtils::formatReqPerS( $c['num_hits'] / $cPeriod );
		$rateMiss = APCUtils::formatReqPerS( $c['num_misses'] / $cPeriod );
		$rateInsert = APCUtils::formatReqPerS( $c['num_inserts'] / $cPeriod );

		$cachedFiles = $context->msg( 'viewapc-cached-files-d' )->numParams( $c['num_entries'] )->
			params( $lang->formatSize( $c['mem_size'] ) )->text();
		$cacheFullCount = $lang->formatNum( $c['expunges'] );

		$contentType = !$user ? 'viewapc-filecache-info' : 'viewapc-usercache-info';
		$contentType = $context->msg( $contentType )->text();

		return
			APCUtils::tableHeader( $contentType ) .
			APCUtils::tableRow( $r = 0, $context->msg( 'viewapc-cached-files' )->escaped(), $cachedFiles ) .
			APCUtils::tableRow( $r = 1 - $r, $context->msg( 'viewapc-hits' )->escaped(), $numHits ) .
			APCUtils::tableRow( $r = 1 - $r, $context->msg( 'viewapc-misses' )->escaped(), $numMiss ) .
			APCUtils::tableRow( $r = 1 - $r, $context->msg( 'viewapc-requests' )->escaped(), $rateReqs ) .
			APCUtils::tableRow( $r = 1 - $r, $context->msg( 'viewapc-hitrate' )->escaped(), $rateHits ) .
			APCUtils::tableRow( $r = 1 - $r, $context->msg( 'viewapc-missrate' )->escaped(), $rateMiss ) .
			APCUtils::tableRow( $r = 1 - $r, $context->msg( 'viewapc-insertrate' )->escaped(), $rateInsert ) .
			APCUtils::tableRow( $r = 1 - $r, $context->msg( 'viewapc-cachefull' )->escaped(), $cacheFullCount ) .
			APCUtils::tableFooter();
	}

	public static function doRuntimeInfoTable( IContextSource $context ) {
		$s = APCUtils::tableHeader( $context->msg( 'viewapc-info-runtime' )->text() );

		$r = 1;
		foreach ( ini_get_all( 'apc' ) as $k => $v ) {
			$s .= APCUtils::tableRow( $r = 1 - $r,
				htmlspecialchars( $k ),
				str_replace( ',', ',<br />', htmlspecialchars( $v['local_value'] ) ) );
		}

		$s .= APCUtils::tableFooter();
		return $s;
	}

	public static function doMemoryInfoTable( $c, $mem, Title $title, IContextSource $context ) {
		$lang = $context->getLanguage();

		$s = APCUtils::tableHeader( $context->msg( 'viewapc-info-memory' )->text(), 'mw-apc-img-table' );

		if ( $mem['num_seg'] > 1 || $mem['num_seg'] == 1 && count( $mem['block_lists'][0] ) > 1 ) {
			$memHeader = $context->msg( 'viewapc-memory-usage-detailed' )->parse();
		} else {
			$memHeader = $context->msg( 'viewapc-memory-usage' )->parse();
		}
		$hitHeader = $context->msg( 'viewapc-cache-efficiency' )->parse();

		$s .= APCUtils::tableRow( null, $memHeader, $hitHeader );


		if ( APCImages::graphics_avail() ) {
			$attribs = array(
				'alt' => '',
				'width' => APCImages::GRAPH_SIZE + 10,
				'height' => APCImages::GRAPH_SIZE + 10,
			);

			$param1 = wfArrayToCgi( array( 'image' => APCImages::IMG_MEM_USAGE ) );
			$param2 = wfArrayToCgi( array( 'image' => APCImages::IMG_HITS ) );

			$attribs1 = array( 'src' => $title->getLocalURL( $param1 ) ) + $attribs;
			$attribs2 = array( 'src' => $title->getLocalURL( $param2 ) ) + $attribs;

			$s .= APCUtils::tableRow( null, Xml::element( 'img', $attribs1 ), Xml::element( 'img', $attribs2 ) );
		}

		$size = $mem['num_seg'] * $mem['seg_size'];
		$free = $mem['avail_mem'];
		$used = $size - $free;

		$freeMem = $context->msg( 'viewapc-memory-free', $lang->formatSize( $free ) )->
			numParams( sprintf( '%.1f%%', $free * 100 / $size ) )->parse();

		$usedMem = $context->msg( 'viewapc-memory-used', $lang->formatSize( $used ) )->
			numParams( sprintf( '%.1f%%', $used * 100 / $size ) )->parse();

		$hits = $c['num_hits'];
		$miss = $c['num_misses'];
		$reqs = $hits + $miss;

		$greenbox = Xml::element( 'span', array( 'class' => 'green box' ), ' ' );
		$redbox = Xml::element( 'span', array( 'class' => 'red box' ), ' ' );

		$memHits = $context->msg( 'viewapc-memory-hits' )->
			numParams( $hits, @sprintf( '%.1f%%', $hits * 100 / $reqs ) )->parse();

		$memMiss = $context->msg( 'viewapc-memory-miss' )->
			numParams( $miss, @sprintf( '%.1f%%', $miss * 100 / $reqs ) )->parse();

		$s .= APCUtils::tableRow( null, $greenbox . $freeMem, $greenbox . $memHits );
		$s .= APCUtils::tableRow( null, $redbox . $usedMem, $redbox . $memMiss );
		$s .= APCUtils::tableFooter();

		return $s;
	}

	public static function doFragmentationTable( $mem, Title $title, IContextSource $context ) {
		$lang = $context->getLanguage();
		$s = APCUtils::tableHeader(
			$context->msg( 'viewapc-memoryfragmentation' )->text(),
			'mw-apc-img-table'
		);
		$s .= Xml::openElement( 'tr' ) . Xml::openElement( 'td' );

		// Fragementation: (freeseg - 1) / total_seg
		$nseg = $freeseg = $fragsize = $freetotal = 0;
		for ( $i = 0; $i < $mem['num_seg']; $i++ ) {
			$ptr = 0;
			foreach ( $mem['block_lists'][$i] as $block ) {
				if ( $block['offset'] != $ptr ) {
					++$nseg;
				}
				$ptr = $block['offset'] + $block['size'];
				/* Only consider blocks <5M for the fragmentation % */
				if ( $block['size'] < ( 5 * 1024 * 1024 ) ) {
					$fragsize += $block['size'];
				}
				$freetotal += $block['size'];
			}
			$freeseg += count( $mem['block_lists'][$i] );
		}

		if ( APCImages::graphics_avail() ) {
			$attribs = array(
				'alt' => '',
				'width' => 2 * APCImages::GRAPH_SIZE + 150,
				'height' => APCImages::GRAPH_SIZE + 10,
				'src' => $title->getLocalURL( 'image=' . APCImages::IMG_FRAGMENTATION )
			);
			$s .= Xml::element( 'img', $attribs );
		}

		if ( $freeseg > 1 ) {
			$fragPercent = sprintf( '%.2f%%', ( $fragsize / $freetotal ) * 100 );
			$s .= $context->msg(
				'viewapc-fragmentation-info',
				$lang->formatNum( $fragPercent, true ),
				$lang->formatSize( $fragsize ),
				$lang->formatSize( $freetotal ),
				$lang->formatNum( $freeseg )
			)->parseAsBlock();
		} else {
			$s .= $context->msg( 'viewapc-fragmentation-none' )->parseAsBlock();
		}

		$s .= Xml::closeElement( 'td' ) . Xml::closeElement( 'tr' );

		if ( isset( $mem['adist'] ) ) {
			foreach ( $mem['adist'] as $i => $v ) {
				$cur = pow( 2, $i );
				$nxt = pow( 2, $i + 1 ) - 1;
				if ( $i == 0 ) {
					$range = "1";
				}
				else {
					$range = "$cur - $nxt";
				}
				$s .= Xml::tags( 'tr', null,
					Xml::tags( 'th', array( 'align' => 'right' ), $range ) .
						Xml::tags( 'td', array( 'align' => 'right' ), $v )
				);
			}
		}

		$s .= APCUtils::tableFooter();
		return $s;
	}
}
