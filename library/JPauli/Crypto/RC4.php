<?php
namespace JPauli\Crypto;

/**
 * RC4 exposed in PHP
 *
 * This is RC4-drop1024 variant
 * Uses an IV
 *
 * Remember that RC4 is flawed and proven to have
 * severals weaknesses. It shouldn't be
 * used anymore now. Prefer ChaCha20 f.e, Spritz
 * is also interesting
 *
 * @author Julien Pauli <jpauli@php.net>
 */
class RC4
{
	const SHA1_SIZE      = 40;
	const RC4_DROP_BYTES = 1024;

	private $S = '';
	private $i = 0,$j = 0;

	public function __construct($iv, $key)
	{
		for($i=0; $i<256; $i++) {
			$this->S .= chr($i);
		}

		/* Let's hash key and IV together */
		$key = sha1($key.$iv);

		for($i=0, $j=0; $i<256; $i++) {
			$j = ($j + ord($key[$i % self::SHA1_SIZE]) + ord($this->S[$i])) & 0xFF;

			$tmp         = $this->S[$i];
			$this->S[$i] = $this->S[$j];
			$this->S[$j] = $tmp;
		}

		/* RC4-drop1024 */
		for ($i=0; $i<self::RC4_DROP_BYTES; $i++) {
			$this->output();
		}
	}

	public function reset($iv, $key)
	{
		$this->__construct($iv, $key);
		return $this;
	}

	public function output()
	{
		$this->i = ($this->i + 1) & 0xFF;
		$this->j = ($this->j + ord($this->S[$this->i])) & 0xFF;

		$tmp               = $this->S[$this->i];
		$this->S[$this->i] = $this->S[$this->j];
		$this->S[$this->j] = $tmp;

		$byte = $this->S[(ord($this->S[$this->i]) + ord($this->S[$this->j])) & 0xFF];

		return $byte;
	}

	public function getState()
	{
		return $this->S;
	}
}