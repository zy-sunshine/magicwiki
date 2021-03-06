<?php
/**
 * @since 2.0
 */
class MapsBaseStrokableElement extends MapsBaseElement implements iStrokableMapElement {

	protected $strokeColor;
	protected $strokeOpacity;
	protected $strokeWeight;

	public function getStrokeColor() {
		return $this->strokeColor;
	}

	public function setStrokeColor( $strokeColor ) {
		$this->strokeColor = trim($strokeColor);
	}

	public function getStrokeOpacity() {
		return $this->strokeOpacity;
	}

	public function setStrokeOpacity( $strokeOpacity ) {
		$this->strokeOpacity = trim($strokeOpacity);
	}

	public function getStrokeWeight() {
		return $this->strokeWeight;
	}

	public function setStrokeWeight( $strokeWeight ) {
		$this->strokeWeight = trim($strokeWeight);
	}

	public function hasText() {
		return !is_null( $this->text ) && $this->text !== '';
	}

	public function hasTitle() {
		return !is_null( $this->title ) && $this->title !== '';
	}

	public function hasStrokeColor() {
		return !is_null( $this->strokeColor ) && $this->strokeColor !== '';
	}

	public function hasStrokeOpacity() {
		return !is_null( $this->strokeOpacity ) && $this->strokeOpacity !== '';
	}

	public function hasStrokeWeight() {
		return !is_null( $this->strokeWeight ) && $this->strokeWeight !== '';
	}

	public function getJSONObject( $defText = '' , $defTitle = '' ) {
		$parentArray = parent::getJSONObject( $defText , $defTitle );
		$array = array(
			'strokeColor' => $this->hasStrokeColor() ? $this->getStrokeColor() : '#FF0000' ,
			'strokeOpacity' => $this->hasStrokeOpacity() ? $this->getStrokeOpacity() : '1' ,
			'strokeWeight' => $this->hasStrokeWeight() ? $this->getStrokeWeight() : '2'
		);
		return array_merge( $parentArray , $array );
	}

}
