<?php
namespace JPauli\Crypto\LFSR;

use JPauli\Crypto\InvalidArgumentException;
use JPauli\Crypto\RuntimeException;

/******************************************************
 * Galois Linear Feedback Shift Register written in PHP
 ******************************************************
 *
 * If you ignore what this crucial structure is, read
 * https://en.wikipedia.org/wiki/Linear_feedback_shift_register
 *
 * This is the basic structure behind pseudo random number generator
 *
 * This is a POC. This should be useless to you.
 * This is usually implemented in C in many crypto libraries, like OpenSSL
 * This can also be embed in hardware, like in DVD players (for CSS),
 *  BR players (AACS), or GSM (A5/1)...
 *
 * Remember that the output of a single LFSR can be easilly
 * reverse engineered by Berlekamp-Massey algo, with only 2n data
 *
 * @author Julien Pauli <jpauli@php.net>
 */
class LFSR
{
	/* We stopped at 32bits, feel free to go until 64bits */
	const LFSR_MAX_DEGREE       = 32;

	const DEFAULT_DISPLAY_SPEED = 1;

	/* You may use the reverse coefficient polynom as well */
	const POLYNOMIAL_PRIME_COEFF = [ 3 => [2],
			4 => [3],                 /* P(X) = 1 + x^3 + x^4 */
			5 => [3],                 /* P(X) = 1 + x^3 + x^5 */
			6 => [5],                 /* P(X) = 1 + x^5 + x^6 */
			7 => [6],                 /* P(X) = 1 + x^6 + x^7 */
			8 => [6,5,4],             /* P(X) = 1 + x^4 + x^5 + x^6 + x^8 */
			9 => [5],                 /* P(X) = 1 + x^5 + x^9 */
			10 => [7],                /* P(X) = 1 + x^7 + x^10 */
			11 => [9],                /* P(X) = 1 + x^9 + x^11 */
			12 => [6,4,1],            /* P(X) = 1 + x^1 + x^4 + x^6 + x^12 */
			13 => [4,3,1],            /* P(X) = 1 + x^1 + x^3 + x^4 + x^13 */
			14 => [5,3,1],            /* P(X) = 1 + x^1 + x^3 + x^5 + x^14 */
			15 => [14],               /* P(X) = 1 + x^14 + x^15 */
			16 => [14,13,11],         /* P(X) = 1 + x^11 + x^13 + x^14 + x^16 */
			17 => [14],               /* P(X) = 1 + x^14 + x^17 */
			18 => [11],               /* P(X) = 1 + x^11 + x^18 */
			19 => [6,2,1],            /* P(X) = 1 + x^1 + x^2 + x^6 + x^19 */
			20 => [17],               /* P(X) = 1 + x^17 + x^20 */
			21 => [19],               /* P(X) = 1 + x^19 + x^21 */
			22 => [21],               /* P(X) = 1 + x^21 + x^22 */
			23 => [18],               /* P(X) = 1 + x^18 + x^23 */
			24 => [23,22,17],         /* P(X) = 1 + x^17 + x^22 + x^23 + x^24 */
			25 => [22],               /* P(X) = 1 + x^22 + x^25 */
			26 => [6,2,1],            /* P(X) = 1 + x^1 + x^2 + x^6 + x^26 */
			27 => [5,2,1],            /* P(X) = 1 + x^1 + x^2 + x^5 + x^27 */
			28 => [25],               /* P(X) = 1 + x^25 + x^28 */
			29 => [27],               /* P(X) = 1 + x^27 + x^29 */
			30 => [6,4,1],            /* P(X) = 1 + x^1 + x^4 + x^6 + x^30 */
			31 => [28],               /* P(X) = 1 + x^28 + x^31 */
			32 => [22,2,1],           /* P(X) = 1 + x^1 + x^2 + x^22 + x^32 */
	];

	/* Display speed */
	private $speed = self::DEFAULT_DISPLAY_SPEED;

	/* Polynomial degree. The higher, the more values
	 * will be generated ( 2^degree - 1 in case of m-sequence)
	 * Should be set between 3 and 32
	 */
	private $degree;

	/* Also called the seed of the generator
	 * Usually, the key is mixed up with some
	 * initialization vector to have a maximum
	 * unguessable starting state */
	private $start;

	private $isPrepared = false;

	/* Feedback function */
	private $ff = 0;

	/* LFSR taps re-entered (feedback function) */
	private $taps = [];

	private $iterations = 0;

	private $currentState;

	private $running = false;

	public function __construct($degree = null, $start = null, $speed = null)
	{
		if ($degree) {
			$this->setDegree($degree);
		}
		if ($start) {
			$this->setStart($start);
		}
		if ($speed) {
			$this->setSpeed($speed);
		}
	}

	public function pause($c = 1)
	{
		usleep($c * 800000 / $this->speed);

		return $this;
	}

	public function setDegree($degree)
	{
		$degree = (int)$degree;
		if ($degree < 3 || $degree > self::LFSR_MAX_DEGREE) {
			throw new InvalidArgumentException("Degree must be between 3 and %d, %d given", self::LFSR_MAX_DEGREE, $degree);
		}

		$this->degree = $degree;

		return $this;
	}

	public function getDegree()
	{
		return $this->degree;
	}

	public function setSpeed($speed)
	{
		$speed = (int)$speed;
		if ($speed < 1 || $speed > 100) {
			throw new InvalidArgumentException("Speed must be between 1 and 100");
		}
		$this->speed = $speed;

		return $this;
	}

	public function getSpeed()
	{
		return $this->speed;
	}

	public function getStart()
	{
		return $this->start;
	}

	public function setStart($start)
	{
		if (!is_numeric($start)) {
			$l = unpack("C*", $start);
			$start = array_sum($l);
		}
		$this->start = (int)$start;

		return $this;
	}

	public function getIterations()
	{
		return $this->iterations;
	}

	public function isRunning()
	{
		return $this->running;
	}

	private function prepare()
	{
		if ($this->isPrepared) {
			return;
		}

		$this->_prepare();
	}

	private function _prepare()
	{
		if (!$this->degree) {
			throw new RuntimeException("Unknown degree");
		}

		if (!$this->start) {
			throw new RuntimeException("Unknown starting stage (seed)");
		}

		$this->taps[] = $this->degree;

		for ($i = 0; $i < count(self::POLYNOMIAL_PRIME_COEFF[$this->degree]); $i++) {
			$this->taps[] = self::POLYNOMIAL_PRIME_COEFF[$this->degree][$i];
			$this->ff |= (1 << self::POLYNOMIAL_PRIME_COEFF[$this->degree][$i]);
		}

		/* LFSR always has first and last bit set */
		$this->ff |= 1 << ($this->degree);
		$this->ff |=1;

		$this->isPrepared = true;
	}

	public function reset()
	{
		$this->_prepare();

		return $this;
	}

	public function run()
	{
		$this->prepare();

		$this->currentState = $this->start;
		$this->iterations   = 0;
		$this->running      = true;

		/* yield initial state */
		yield $this->iterations => $this->currentState;

		do {
			$this->iterations++;
			$this->currentState >>= 1; /* Shift register */

			yield $this->iterations => $this->currentState;

			if ($this->currentState & 1) {
				$this->currentState ^= $this->ff; /* re-enter as Galois */
			}

		} while ($this->currentState != $this->start);

		$this->running = false;
	}

	public function demoRun()
	{
		$this->prepare();

		echo "\n\n";

		printf("**Simple Galois LFSR, degree %d (%d states m-sequence)**\n", $this->getDegree(), 2 ** $this->getDegree() - 1);
		printf("Used register bits for feedback : %s\n", implode(' ', $this->taps));
		printf("Deducted Feedback function      : %b (0X%1\$X) \n", $this->ff >> 1);

		echo "\n\n";

		$this->pause(4);

		printf("Your initial state is : %032b (%1\$u)\n", $this->start);
		printf("Let's now start the Linear Feedback Shift Register\n");

		$this->pause(4);

		echo "\n\n";

		printf("[Iteration] [-------Internal Register -------] [PRandom bit]\n");
		printf("    |                      |                        |       \n");
		printf("    v                      v                        v       \n");

		yield from $this->run();
	}

	public function getCurrentState()
	{
		return $this->currentState;
	}

	public function getCurrentBit()
	{
		return $this->currentState & 1;
	}

	public function printCurrentState()
	{
		if (!$this->running) {
			return;
		}

		printf("%10d - %032b     [ %d ]\n", $this->iterations, $this->currentState, $this->getCurrentBit());

		return $this;
	}

	public function __debugInfo()
	{
		/* Hide internal state */
		return [];
	}
}