<?php
namespace Espressioni;

use Closure;
use ReflectionFunction;

/*
================================================================================

Expression - PHP Class to safely evaluate math and boolean expressions
Copyright (C) 2005, Miles Kaufmann <http://www.twmagic.com/>
Copyright (C) 2016, Jakub Jankiewicz <http://jcubic.pl/>

================================================================================

NAME
	Expression - safely evaluate math and boolean expressions

SYNOPSIS
	<?
		include('expression.php');
		$e = new Expression();
		// basic evaluation:
		$result = $e->evaluate('2+2');
		// supports: order of operation; parentheses; negation; built-in functions
		$result = $e->evaluate('-8(5/2)^2*(1-sqrt(4))-8');
		// support of booleans
		$result = $e->evaluate('10 < 20 || 20 > 30 && 10 == 10');
		// support for strings and match (regexes can be like in php or like in javascript)
		$result = $e->evaluate('"Foo,Bar" =~ /^([fo]+),(bar)$/i');
		// previous call will create $0 for whole match match and $1,$2 for groups
		$result = $e->evaluate('$2');
		// create your own variables
		$e->evaluate('a = e^(ln(pi))');
		// or functions
		$e->evaluate('f(x,y) = x^2 + y^2 - 2x*y + 1');
		// and then use them
		$result = $e->evaluate('3*f(42,a)');
		// create external functions
		$e->functions['foo'] = function() {
			return "foo";
		};
		// and use it
		$result = $e->evaluate('foo()');
	?>

DESCRIPTION
	Use the Expression class when you want to evaluate mathematical or boolean
	expressions  from untrusted sources.  You can define your own variables and
	functions, which are stored in the object.  Try it, it's fun!

	Based on http://www.phpclasses.org/browse/file/11680.html, cred to Miles Kaufmann

METHODS
	$e->evalute($expr)
		Evaluates the expression and returns the result.  If an error occurs,
		prints a warning and returns false.  If $expr is a function assignment,
		returns true on success.

	$e->e($expr)
		A synonym for $e->evaluate().

	$e->vars()
		Returns an associative array of all user-defined variables and values.

	$e->funcs()
		Returns an array of all user-defined functions.

PARAMETERS
	$e->supressErrors
		Set to true to turn off warnings when evaluating expressions

	$e->lastError
		If the last evaluation failed, contains a string describing the error.
		(Useful when supressErrors is on).

AUTHORS INFORMATION
	Copyright (c) 2005, Miles Kaufmann
	Copyright (c) 2016, Jakub Jankiewicz

LICENSE
	Redistribution and use in source and binary forms, with or without
	modification, are permitted provided that the following conditions are
	met:

	1   Redistributions of source code must retain the above copyright
		notice, this list of conditions and the following disclaimer.
	2.  Redistributions in binary form must reproduce the above copyright
		notice, this list of conditions and the following disclaimer in the
		documentation and/or other materials provided with the distribution.
	3.  The name of the author may not be used to endorse or promote
		products derived from this software without specific prior written
		permission.

	THIS SOFTWARE IS PROVIDED BY THE AUTHOR ``AS IS'' AND ANY EXPRESS OR
	IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE IMPLIED
	WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE ARE
	DISCLAIMED. IN NO EVENT SHALL THE AUTHOR BE LIABLE FOR ANY DIRECT,
	INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES
	(INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR
	SERVICES; LOSS OF USE, DATA, OR PROFITS; OR BUSINESS INTERRUPTION)
	HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN CONTRACT,
	STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN
	ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE
	POSSIBILITY OF SUCH DAMAGE.
*/

/**
 *	For more details, see notice above.
 *	Adapted for using PHP 7.4 improvements
 *
 *	@author Miles Kaufmann <http://www.twmagic.com/>
 *	@author Jakub Jankiewicz <http://jcubic.pl/>
 *	@author Collei Inc. <collei@collei.com.br>
 *	@since respectively: 2005, 2016, 2022
 *	
 */
class Expression
{
	/**
	 *	regex expressions
	 */
	private const REGEX_ASSIGN_VAR = '/^\s*([a-z]\w*)\s*=(?!~|=)\s*(.+)$/';
	private const REGEX_ASSIGN_FUNCTION = '/^\s*([a-z]\w*)\s*\((?:\s*([a-z]\w*(?:\s*,\s*[a-z]\w*)*)\s*)?\)\s*=(?!~|=)\s*(.+)$/';
	private const REGEX_OPERATORS = "/^[+\-*\/^_\"<>=%(){\[!~,](?!=|~)/";
	private const REGEX_SINGLE_STR = '(?<!\\\\)"(?:(?:(?<!\\\\)(?:\\\\{2})*\\\\)"|[^"])*(?<![^\\\\]\\\\)"';
	private const REGEX_DOUBLE_STR = "(?<!\\\\)'(?:(?:(?<!\\\\)(?:\\\\{2})*\\\\)'|[^'])*(?<![^\\\\]\\\\)'";
	private const REGEX_REGEX = "(?<!\\\\)\/(?:[^\/]|\\\\\/)+\/[imsxUXJ]*";
	private const REGEX_JSON = '[\[{](?>"(?:[^"]|\\\\")*"|[^[{\]}]|(?1))*[\]}]';
	private const REGEX_NUMBER = '[\d.]+e\d+|\d+(?:\.\d*)?|\.\d+';
	private const REGEX_NAME = '[a-z]\w*\(?|\\$\w+';
	private const REGEX_PARENTHESIS = '\\(';

	/**
	 *	binary operator list
	 */
	private const BINARY_OPERATORS = [
		'+', '-', '*', '/', '^', '<', '>', '<=', '>=',
		'==', '&&', '||', '!=', '=~', '%'
	];

	/**
	 *	@var bool $supressErrors
	 */
	public $supressErrors = false;

	/**
	 *	@var mixed $lastError
	 */
	private $lastError = null;

	/**
	 *	@var array $v
	 *		expression variables and constants
	 */
	private $v = [
		'e' => 2.71,
		'pi' => 3.14,
	];

	/**
	 *	@var array $f
	 *		user-defined functions
	 */
	private $f = [];

	/**
	 *	@var array $f
	 *		constants
	 */
	private $vb = [
		'e',
		'pi',
	];

	/**
	 *	@var array $constants
	 *		constants
	 */
	private $constants = [
		'e',
		'pi',
	];

	/**
	 *	@var array $f
	 *		built-in functions
	 */
	private $fb = [
		'sin', 'sinh', 'arcsin', 'asin', 'arcsinh', 'asinh',
		'cos', 'cosh', 'arccos', 'acos', 'arccosh', 'acosh',
		'tan', 'tanh', 'arctan', 'atan', 'arctanh', 'atanh',
		'sqrt', 'abs', 'ln', 'log'
	];

	/**
	 *	@var array $f
	 *		functions defined outside of Expression as closures
	 */
	private $functions = [];

	/**
	 *	Initializes internal environment
	 */
	public function __construct()
	{
		// make the variables a little more accurate
		$this->v['pi'] = pi();
		$this->v['e'] = exp(1);
	}

	/**
	 *	Publishes private fields as readonly properties
	 */
	public function __get(string $name)
	{
		if ($name == 'lastError') {
			return $this->lastError;
		}
	}

	/**
	 *	Creates Closure functions
	 *
	 *	@param	string	$name
	 *	@param	\Closure	$closure
	 *	@return	mixed
	 */
	public function addExternalFunction(string $name, Closure $closure)
	{
		$this->functions[$name] = $closure;
	}


	/**
	 *	Evaluates expressions. Shorthand for evaluate().
	 *
	 *	@param	mixed	$expr
	 *	@return	mixed
	 */
	public function e($expr)
	{
		return $this->evaluate($expr);
	}

	/**
	 *	Evaluates expressions.
	 *
	 *	@param	mixed	$expr
	 *	@return	mixed
	 */
	public function evaluate($expr)
	{
		$this->lastError = null;
		$expr = trim($expr);
		if (substr($expr, -1, 1) == ';') {
			// strip semicolons at the end
			$expr = substr($expr, 0, strlen($expr)-1);
		}
		//===============
		// is it a variable assignment?
		if (preg_match(self::REGEX_ASSIGN_VAR, $expr, $matches)) {
			if (in_array($matches[1], $this->vb)) { // make sure we're not assigning to a constant
				return $this->trigger("cannot assign to constant '$matches[1]'");
			}
			$tmp = $this->pfx($this->nfx($matches[2]));
			$this->v[$matches[1]] = $tmp; // if so, stick it in the variable array
			return $this->v[$matches[1]]; // and return the resulting value
		//===============
		// is it a function assignment?
		} elseif (preg_match(self::REGEX_ASSIGN_FUNCTION, $expr, $matches)) {
			$fnn = $matches[1]; // get the function name
			if (in_array($matches[1], $this->fb)) { // make sure it isn't built in
				return $this->trigger("cannot redefine built-in function '$matches[1]()'");
			}
			//
			if ($matches[2] != "") {
				$args = explode(",", preg_replace("/\s+/", "", $matches[2])); // get the arguments
			} else {
				$args = array();
			}
			//
			if (($stack = $this->nfx($matches[3])) === false) {
				// see if it can be converted to postfix
				return false;
			}
			//
			for ($i = 0; $i<count($stack); $i++) { // freeze the state of the non-argument variables
				$token = $stack[$i];
				if (preg_match('/^[a-z]\w*$/', $token) and !in_array($token, $args)) {
					if (array_key_exists($token, $this->v)) {
						$stack[$i] = $this->v[$token];
					} else {
						return $this->trigger("undefined variable '$token' in function definition");
					}
				}
			}
			$this->f[$fnn] = array('args'=>$args, 'func'=>$stack);
			return true;
		//===============
		} else {
			return $this->pfx($this->nfx($expr)); // straight up evaluation, woo
		}
	}

	/**
	 *	Returns a list of current set variables.
	 *
	 *	@return	array
	 */
	public function vars()
	{
		$output = $this->v;
		//
		foreach ($this->constants as $constant) {
			unset($output[$constant]);
		}
		//
		return $output;
	}

	/**
	 *	Returns a list of current set functions
	 *
	 *	@return	array
	 */
	public function funcs()
	{
		$output = array();
		//
		foreach ($this->f as $fnn => $dat) {
			$output[] = $fnn . '(' . implode(',', $dat['args']) . ')';
		}
		//
		return $output;
	}

	//===================== HERE BE INTERNAL METHODS ====================\\

	/**
	 *	Convert infix to postfix notation
	 *
	 *	@param	mixed	$expr
	 *	@return	mixed
	 */
	private function nfx($expr)
	{
		$index = 0;
		$stack = new ExpressionStack;
		$output = []; // postfix form of expression, to be passed to pfx()
		$expr = trim($expr);
		//
		$ops   = [
			'+', '-', '*', '/', '^', '_', '%', '>', '<', '>=', '<=',
			'==', '!=', '=~', '&&', '||', '!'
		];
		// right-associative operator?
		$ops_r = [
			'+' => 0, '-' => 0, '*' => 0, '/' => 0, '%' => 0, '^' => 1,
			'>' => 0, '<' => 0, '>=' => 0, '<=' => 0, '==' => 0, '!=' => 0,
			'=~' => 0, '&&' => 0, '||' => 0, '!' => 0
		];
		// operator precedence
		$ops_p = [
			'+' => 3, '-' => 3, '*' => 4, '/' => 4, '_' => 4, '%' => 4,
			'^' => 5, '>' => 2, '<' => 2, '>=' => 2, '<=' => 2, '==' => 2,
			'!=' => 2, '=~' => 2, '&&' => 1, '||' => 1, '!' => 5
		];

		$expecting_op = false; // we use this in syntax-checking the expression
							   // and determining when a - is a negation

		/* we allow all characters because of strings
		if (preg_match("%[^\w\s+*^\/()\.,-<>=&~|!\"\\\\/]%", $expr, $matches)) {
			// make sure the characters are all good
			return $this->trigger("illegal character '{$matches[0]}'");
		}
		*/
		$first_argument = false;
		$i = 0;
		$matcher = false;
		while(1) { // 1 Infinite Loop ;)
			// get the first two characters at the current index
			$op = substr(substr($expr, $index), 0, 2);
			if (
				preg_match(self::REGEX_OPERATORS, $op) || preg_match("/\w/", $op)
			) {
				// fix $op if it should have one character
				$op = substr($expr, $index, 1);
			}
			//
			$single_str = self::REGEX_SINGLE_STR;
			$double_str = self::REGEX_DOUBLE_STR;
			$regex = self::REGEX_REGEX;
			$json = self::REGEX_JSON;
			$number = self::REGEX_NUMBER;
			$name = self::REGEX_NAME;
			$parenthesis = self::REGEX_PARENTHESIS;
			// find out if we're currently at the beginning of a 
			// number/string/object/array/variable/function/parenthesis/operand
			$ex = preg_match(
				"%^($single_str|$double_str|$json|$name|$regex|$number|$parenthesis)%",
				substr($expr, $index),
				$match
			);
			/*
			if ($i++ > 1000) {
				break;
			}
			if ($ex) {
				print_r($match);
			} else {
				echo json_encode($op) . "\n";
			}
			echo $index . "\n";
			*/
			//===============
			if ($op == '[' && $expecting_op && $ex) {
				if (!preg_match("/^\[(.*)\]$/", $match[1], $matches)) {
					return $this->trigger("invalid array access");
				}
				$stack->push('[');
				$stack->push($matches[1]);
				$index += strlen($match[1]);
				//} elseif ($op == '!' && !$expecting_op) {
				//	$stack->push('!'); // put a negation on the stack
				//	$index++;
			} elseif ($op == '-' and !$expecting_op) {
				// is it a negation instead of a minus?
				// put a negation on the stack
				$stack->push('_'); 
				$index++;
			} elseif ($op == '_') {
				// we have to explicitly deny this,
				// because it's legal on the stack
				// but not in the input expression
				return $this->trigger("illegal character '_'");
			} elseif (
				$ex && $matcher && preg_match("%^" . $regex . "$%", $match[1])
			) {
				$stack->push('"' . $match[1] . '"');
				$index += strlen($match[1]);
				$op = null;
				$expecting_op = false;
				$matcher = false;
				break;
			//===============
			} elseif (
				((in_array($op, $ops) or $ex) and $expecting_op) or
				in_array($op, $ops) and !$expecting_op or
				(!$matcher && $ex && preg_match("%^" . $regex . "$%", $match[1]))
			) {
				// heart of the algorithm:
				while (
					$stack->count > 0 
					and ($o2 = $stack->last())
					and in_array($o2, $ops)
					and (($ops_r[$op] ?? 0) ? (($ops_p[$op] ?? 0) < ($ops_p[$o2] ?? 0)) : (($ops_p[$op] ?? 0) <= ($ops_p[$o2] ?? 0)))
				) {
					$output[] = $stack->pop(); // pop stuff off the stack into the output
				}
				// many thanks: http://en.wikipedia.org/wiki/Reverse_Polish_notation#The_algorithm_in_detail
				// finally put OUR operator onto the stack
				$stack->push($op); 
				$index += strlen($op);
				$expecting_op = false;
				$matcher = $op == '=~';
			//===============
			} elseif ($op == ')' and $expecting_op || !$ex) { // ready to close a parenthesis?
				$arg_count = 0;
				while (($o2 = $stack->pop()) != '(') { // pop off the stack back to the last (
					if (is_null($o2)) {
						return $this->trigger("unexpected ')'");
					} else {
						$arg_count++;
						$output[] = $o2;
					}
				}
				// did we just close a function?
				if (preg_match("/^([a-z]\w*)\($/", $stack->last(2), $matches)) {
					// get the function name
					$fnn = $matches[1];
					// see how many arguments there were
					// (cleverly stored on the stack, thank you)
					$arg_count += $stack->pop();
					// pop the function and push onto the output
					$output[] = $stack->pop();
					// check the argument count
					if (in_array($fnn, $this->fb)) {
						if($arg_count > 1) {
							return $this->trigger(
								"too many arguments ($arg_count given, 1 expected)"
							);
						}
					} elseif (array_key_exists($fnn, $this->f)) {
						if ($arg_count != count($this->f[$fnn]['args']))
							return $this->trigger(
								"wrong number of arguments ($arg_count given, "
								. count($this->f[$fnn]['args'])
								. " expected) "
								. json_encode($this->f[$fnn]['args'])
							);
					} elseif (array_key_exists($fnn, $this->functions)) {
						$func_reflection = new ReflectionFunction($this->functions[$fnn]);
						$count = $func_reflection->getNumberOfParameters();
						if ($arg_count != $count)
							return $this->trigger(
								"wrong number of arguments ($arg_count given, $count expected)"
							);
					} else {
						// did we somehow push a non-function on the stack?
						// this should never happen
						return $this->trigger("internal error @ " . __LINE__);
					}
				}
				$index++;
			//===============
			} elseif ($op == ',' and $expecting_op) {
				// did we just finish a function argument?
				while (($o2 = $stack->pop()) != '(') {
					if (is_null($o2)) {
						// oops, never had a (
						return $this->trigger("unexpected ','");
					} else {
						// pop the argument expression stuff
						// and push onto the output
						$output[] = $o2;
					}
				}
				// make sure there was a function
				if (!preg_match("/^([a-z]\w*)\($/", $stack->last(2), $matches)) {
					return $this->trigger("unexpected ','");
				}
				//
				if ($first_argument) {
					$first_argument = false;
				} else {
					// increment the argument count
					$stack->push($stack->pop()+1);
				}
				// put the ( back on, we'll need to pop back to it again
				$stack->push('(');
				//
				$index++;
				$expecting_op = false;
			//===============
			} elseif ($op == '(' and !$expecting_op) {
				$stack->push('('); // that was easy
				$index++;
				$allow_neg = true;
			//===============
			} elseif ($ex and !$expecting_op) {
				// do we now have a function/variable/number?
				$expecting_op = true;
				$val = $match[1];
				if ($op == '[' || $op == "{" || preg_match("/null|true|false/", $match[1])) {
					$output[] = $val;
				} elseif (preg_match("/^([a-z]\w*)\($/", $val, $matches)) {
					// may be func, or variable w/ implicit multiplication
					// against parentheses...
					if (
						in_array($matches[1], $this->fb) or
						array_key_exists($matches[1], $this->f) or
						array_key_exists($matches[1], $this->functions)
					) { // it's a func
						$stack->push($val);
						$stack->push(0);
						$stack->push('(');
						$expecting_op = false;
					} else { // it's a var w/ implicit multiplication
						$val = $matches[1];
						$output[] = $val;
					}
				} else { // it's a plain old var or num
					$output[] = $val;
					if (preg_match("/^([a-z]\w*)\($/", $stack->last(3))) {
						$first_argument = true;
						while (($o2 = $stack->pop()) != '(') {
							if (is_null($o2)) {
								// oops, never had a (
								return $this->trigger("unexpected error");
							} else {
								// pop the argument expression stuff
								// and push onto the output
								$output[] = $o2;
							}
						}
						// make sure there was a function
						if (!preg_match("/^([a-z]\w*)\($/", $stack->last(2), $matches)) {
							return $this->trigger("unexpected error");
						}
						//
						// increment the argument count
						$stack->push($stack->pop()+1);
						// put the ( back on, we'll need to pop back to it again
						$stack->push('(');
					}
				}
				$index += strlen($val);
			//===============
			} elseif ($op == ')') { // miscellaneous error checking
				return $this->trigger("unexpected ')'");
			} elseif (in_array($op, $ops) and !$expecting_op) {
				return $this->trigger("unexpected operator '$op'");
			} else { // I don't even want to know what you did to get here
				return $this->trigger(
					"an unexpected error occured " . json_encode($op)
					. " " . json_encode($match)
					. " ". ($ex?'true':'false') . " " . $expr
				);
			}
			if ($index == strlen($expr)) {
				// did we end with an operator? bad.
				if (in_array($op, $ops)) { 
					return $this->trigger("operator '$op' lacks operand");
				} else {
					break;
				}
			}
			// step the index past whitespace (pretty much turns whitespace
			// into implicit multiplication if no operator is there)
			while (substr($expr, $index, 1) == ' ') {
				$index++;
			}

		}
		// pop everything off the stack and push onto output
		while (!is_null($op = $stack->pop())) {
			// if there are (s on the stack, ()s were unbalanced
			if ($op == '(') {
				return $this->trigger("expecting ')'");
			}
			$output[] = $op;
		}
		//
		return $output;
	}

	/**
	 *	Evaluate postfix notation
	 *
	 *	@param	mixed	$tokens
	 *	@param	mixed	$vars
	 *	@return	mixed
	 */
	private function pfx($tokens, $vars = [])
	{
		if ($tokens == false) {
			return false;
		}
		//
		$stack = new ExpressionStack();
		//
		foreach ($tokens as $token) { // nice and easy
			// if the token is a binary operator, pop two values off the stack, do the operation, and push the result back on
			if (in_array($token, self::BINARY_OPERATORS)) {
				$op2 = $stack->pop();
				$op1 = $stack->pop();
				switch ($token) {
					case '+':
						if (is_string($op1) || is_string($op2)) {
							$stack->push((string)$op1 . (string)$op2);
						} else {
							$stack->push($op1 + $op2);
						}
						break;
					case '-':
						$stack->push($op1 - $op2);
						break;
					case '*':
						$stack->push($op1 * $op2);
						break;
					case '/':
						if ($op2 == 0) return $this->trigger("division by zero");
						$stack->push($op1 / $op2);
						break;
					case '%':
						$stack->push($op1 % $op2);
						break;
					case '^':
						$stack->push(pow($op1, $op2));
						break;
					case '>':
						$stack->push($op1 > $op2);
						break;
					case '<':
						$stack->push($op1 < $op2);
						break;
					case '>=':
						$stack->push($op1 >= $op2);
						break;
					case '<=':
						$stack->push($op1 <= $op2);
						break;
					case '==':
						if (is_array($op1) && is_array($op2)) {
							$stack->push(json_encode($op1) == json_encode($op2));
						} else {
							$stack->push($op1 == $op2);
						}
						break;
					case '!=':
						if (is_array($op1) && is_array($op2)) {
							$stack->push(json_encode($op1) != json_encode($op2));
						} else {
							$stack->push($op1 != $op2);
						}
						break;
					case '=~':
						$value = @preg_match($op2, $op1, $match);

						if (!is_int($value)) {
							return $this->trigger("Invalid regex " . json_encode($op2));
						}
						$stack->push($value);
						for ($i = 0; $i < count($match); $i++) {
							$this->v['$' . $i] = $match[$i];
						}
						break;
					case '&&':
						$stack->push($op1 ? $op2 : $op1);
						break;
					case '||':
						$stack->push($op1 ? $op1 : $op2);
						break;
				}
			// if the token is a unary operator, pop one value off the stack, do the operation, and push it back on
			} elseif ($token == '!') {
				$stack->push(!$stack->pop());
			} elseif ($token == '[') {
				$selector = $stack->pop();
				$object = $stack->pop();
				if (is_object($object)) {
					$stack->push($object->$selector);
				} elseif (is_array($object)) {
					$stack->push($object[$selector]);
				} else {
					return $this->trigger("invalid object for selector");
				}
			} elseif ($token == "_") {
				$stack->push(-1*$stack->pop());
			// if the token is a function, pop arguments off the stack, hand them to the function, and push the result back on
			} elseif (preg_match("/^([a-z]\w*)\($/", $token, $matches)) { // it's a function!
				$fnn = $matches[1];
				if (in_array($fnn, $this->fb)) { // built-in function:
					if (is_null($op1 = $stack->pop())) {
						return $this->trigger("internal error @ " . __LINE__);
					}
					$fnn = preg_replace("/^arc/", "a", $fnn); // for the 'arc' trig synonyms
					if ($fnn == 'ln') $fnn = 'log';
					$stack->push($fnn($op1)); // perfectly safe variable function call
				} elseif (array_key_exists($fnn, $this->f)) { // user function
					// get args
					$args = [];
					for ($i = count($this->f[$fnn]['args'])-1; $i >= 0; $i--) {
						if ($stack->empty()) {
							return $this->trigger(
								"internal error " . $fnn . " "
								. json_encode($this->f[$fnn]['args'])
							);
						}
						$args[$this->f[$fnn]['args'][$i]] = $stack->pop();
					}
					$stack->push($this->pfx($this->f[$fnn]['func'], $args)); // yay... recursion!!!!
				} else if (array_key_exists($fnn, $this->functions)) {
					$reflection = new ReflectionFunction($this->functions[$fnn]);
					$count = $reflection->getNumberOfParameters();
					$args = [];
					for ($i = $count-1; $i >= 0; $i--) {
						if ($stack->empty()) {
							return $this->trigger("internal error @ " . __LINE__);
						}
						$args[] = $stack->pop();
					}
					$stack->push($reflection->invokeArgs($args));
				}
			// if the token is a number or variable, push it on the stack
			} else {
				if (preg_match('/^([\[{](?>"(?:[^"]|\\")*"|[^[{\]}]|(?1))*[\]}])$/', $token) ||
					preg_match("/^(null|true|false)$/", $token)) { // json
					//return $this->trigger("invalid json " . $token);
					if ($token == 'null') {
						$value = null;
					} elseif ($token == 'true') {
						$value = true;
					} elseif ($token == 'false') {
						$value = false;
					} else {
						$value = json_decode($token);
						if ($value == null) {
							return $this->trigger("invalid json " . $token);
						}
					}
					$stack->push($value);
				} elseif (is_numeric($token)) {
					$stack->push(0+$token);
				} else if (preg_match("/^['\\\"](.*)['\\\"]$/", $token)) {
					$stack->push(json_decode(preg_replace_callback("/^['\\\"](.*)['\\\"]$/", function($matches) {
						$m = array("/\\\\'/", '/(?<!\\\\)"/');
						$r = array("'", '\\"');
						return '"' . preg_replace($m, $r, $matches[1]) . '"';
					}, $token)));
				} elseif (array_key_exists($token, $this->v)) {
					$stack->push($this->v[$token]);
				} elseif (array_key_exists($token, $vars)) {
					$stack->push($vars[$token]);
				} else {
					return $this->trigger("undefined variable '$token'");
				}
			}
		}
		// when we're out of tokens,
		// the stack should have a single element, the final result
		if ($stack->count != 1) {
			return $this->trigger("internal error @ " . __LINE__);
		}
		//
		return $stack->pop();
	}

	/**
	 *	Trigger an error, but nicely, if need be
	 *
	 *	@param	mixed	$msg
	 *	@return	mixed
	 */
	private function trigger($msg)
	{
		$this->lastError = $msg;
		//
		if (!$this->supressErrors) {
			trigger_error($msg, E_USER_WARNING);
		}
		//
		return false;
	}
}

// for internal use
class ExpressionStack
{
	private $stack = [];
	private $count = 0;

	public function __get(string $name)
	{
		if ($name == 'count') {
			return $this->count;
		}
		if ($name == 'stack') {
			return $this->stack;
		}
	}

	function push($val)
	{
		$this->stack[$this->count] = $val;
		$this->count++;
	}
	
	function pop()
	{
		if ($this->count > 0) {
			$this->count--;
			return $this->stack[$this->count];
		}
		//
		return null;
	}

	function empty()
	{
		return empty($this->stack);
	}

	function last($n = 1)
	{
		if (isset($this->stack[$this->count - $n])) {
			return $this->stack[$this->count - $n];
		}
		//
		return;
	}
}


