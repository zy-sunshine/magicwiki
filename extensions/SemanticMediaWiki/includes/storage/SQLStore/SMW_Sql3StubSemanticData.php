<?php
/**
 * @file
 * @ingroup SMWStore
 * @since 1.8
 */

/**
 * This class provides a subclass of SMWSemanticData that can store
 * prefetched values from SMW's SQL stores, and unstub this data on demand when
 * it is accessed.
 * 
 * @since 1.8
 * @author Markus Krötzsch
 *
 * @ingroup SMWStore
 */
class SMWSql3StubSemanticData extends SMWSemanticData {

	/**
	 * The store object.
	 *
	 * @since 1.8
	 *
	 * @var SMWSQLStore3
	 */
	protected $store;

	/**
	 * Stub property data that is not part of $mPropVals and $mProperties
	 * yet. Entries use property keys as keys. The value is an array of
	 * DBkey-arrays that define individual datavalues. The stubs will be
	 * set up when first accessed.
	 *
	 * @since 1.8
	 *
	 * @var array
	 */
	protected $mStubPropVals = array();

	/**
	 * SMWDIWikiPage object that is the subject of this container.
	 * Subjects that are null are used to represent "internal objects"
	 * only.
	 *
	 * @since 1.8
	 *
	 * @var SMWDIWikiPage
	 */
	protected $mSubject;

	/**
	 * Constructor.
	 *
	 * @since 1.8
	 *
	 * @param SMWDIWikiPage $subject to which this data refers
	 * @param SMWSQLStore3 $store (the parent store)
	 * @param boolean $noDuplicates stating if duplicate data should be avoided
	 */
	public function __construct( SMWDIWikiPage $subject, SMWSQLStore3 $store, $noDuplicates = true ) {
		$this->store = $store;
		parent::__construct( $subject, $noDuplicates );
	}

	/**
	 * Create a new SMWSql3StubSemanticData object that holds the data of a
	 * given SMWSemanticData object. Array assignments create copies in PHP
	 * so the arrays are distinct in input and output object. The object
	 * references are copied as references in a shallow way. This is
	 * sufficient as the data items used there are immutable.
	 *
	 * @since 1.8
	 *
	 * @param $semanticData SMWSemanticData
	 * @param SMWSQLStore3 $store
	 *
	 * @return SMWSql3StubSemanticData
	 */
	public static function newFromSemanticData( SMWSemanticData $semanticData, SMWSQLStore3 $store ) {
		$result = new SMWSql3StubSemanticData( $semanticData->getSubject(), $store );
		$result->mPropVals = $semanticData->mPropVals;
		$result->mProperties = $semanticData->mProperties;
		$result->mHasVisibleProps = $semanticData->mHasVisibleProps;
		$result->mHasVisibleSpecs = $semanticData->mHasVisibleSpecs;
		$result->stubObject = $semanticData->stubObject;
		return $result;
	}

	/**
	 * Get the array of all properties that have stored values.
	 *
	 * @since 1.8
	 *
	 * @return array of SMWDIProperty objects
	 */
	public function getProperties() {
		$this->unstubProperties();
		return parent::getProperties();
	}

	/**
	 * Get the array of all stored values for some property.
	 *
	 * @since 1.8
	 *
	 * @param $property SMWDIProperty
	 *
	 * @return array of SMWDataItem
	 */
	public function getPropertyValues( SMWDIProperty $property ) {
		if ( $property->isInverse() ) { // we never have any data for inverses
			return array();
		}

		if ( array_key_exists( $property->getKey(), $this->mStubPropVals ) ) {
			$this->unstubProperty( $property->getKey(), $property );
			$propertyTypeId = $property->findPropertyTypeID();
			$propertyDiId = SMWDataValueFactory::getDataItemId( $propertyTypeId );

			foreach ( $this->mStubPropVals[$property->getKey()] as $dbkeys ) {
				try {
					$diHandler = $this->store->getDataItemHandlerForDIType( $propertyDiId );
					$di = $diHandler->dataItemFromDBKeys( $dbkeys );

					if ( $this->mNoDuplicates ) {
						$this->mPropVals[$property->getKey()][$di->getHash()] = $di;
					} else {
						$this->mPropVals[$property->getKey()][] = $di;
					}
				} catch ( SMWDataItemException $e ) {
					// ignore data
				}
			}

			unset( $this->mStubPropVals[$property->getKey()] );
		}

		return parent::getPropertyValues( $property );
	}

	/**
	 * Remove a value for a property identified by its SMWDataItem object.
	 * This method removes a property-value specified by the property and
	 * dataitem. If there are no more property-values for this property it
	 * also removes the property from the mProperties.
	 *
	 * @note There is no check whether the type of the given data item
	 * agrees with the type of the property. Since property types can
	 * change, all parts of SMW are prepared to handle mismatched data item
	 * types anyway.
	 *
	 * @param $property SMWDIProperty
	 * @param $dataItem SMWDataItem
	 *
	 * @since 1.8
	 */
	public function removePropertyObjectValue( SMWDIProperty $property, SMWDataItem $dataItem ) {
		$this->unstubProperties();
		$this->getPropertyValues( $property );
		parent::removePropertyObjectValue($property, $dataItem);
	}

	/**
	 * Return true if there are any visible properties.
	 *
	 * @since 1.8
	 *
	 * @return boolean
	 */
	public function hasVisibleProperties() {
		$this->unstubProperties();
		return parent::hasVisibleProperties();
	}

	/**
	 * Return true if there are any special properties that can
	 * be displayed.
	 *
	 * @since 1.8
	 *
	 * @return boolean
	 */
	public function hasVisibleSpecialProperties() {
		$this->unstubProperties();
		return parent::hasVisibleSpecialProperties();
	}

	/**
	 * Add data in abbreviated form so that it is only expanded if needed. The property key
	 * is the DB key (string) of a property value, whereas valuekeys is an array of DBkeys for
	 * the added value that will be used to initialize the value if needed at some point.
	 *
	 * @since 1.8
	 *
	 * @param $propertyKey string
	 * @param $valueKeys array
	 */
	public function addPropertyStubValue( $propertyKey, array $valueKeys ) {
		$this->mStubPropVals[$propertyKey][] = $valueKeys;
	}

	/**
	 * Delete all data other than the subject.
	 *
	 * @since 1.8
	 */
	public function clear() {
		$this->mStubPropVals = array();
		parent::clear();
	}

	/**
	 * Process all mProperties that have been added as stubs.
	 * Associated data may remain in stub form.
	 *
	 * @since 1.8
	 */
	protected function unstubProperties() {
		foreach ( $this->mStubPropVals as $pkey => $values ) { // unstub property values only, the value lists are still kept as stubs
			$this->unstubProperty( $pkey );
		}
	}

	/**
	 * Unstub a single property from the stub data array. If available, an
	 * existing object for that property might be provided, so we do not
	 * need to make a new one. It is not checked if the object matches the
	 * property name.
	 *
	 * @since 1.8
	 *
	 * @param $propertyKey string
	 * @param $diProperty SMWDIProperty
	 */
	protected function unstubProperty( $propertyKey, $diProperty = null ) {
		if ( !array_key_exists( $propertyKey, $this->mProperties ) ) {
			if ( is_null( $diProperty ) ) {
				//$propertyDV = SMWPropertyValue::makeProperty( $propertyKey );
				//$diProperty = $propertyDV->getDataItem();
				$diProperty = new SMWDIProperty( $propertyKey, false );
			}

			$this->mProperties[$propertyKey] = $diProperty;

			if ( !$diProperty->isUserDefined() ) {
				if ( $diProperty->isShown() ) {
					$this->mHasVisibleSpecs = true;
					$this->mHasVisibleProps = true;
				}
			} else {
				$this->mHasVisibleProps = true;
			}
		}
	}

}
