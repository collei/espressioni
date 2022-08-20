# Espressioni
Re-encapsulation of Expression Evaluator

## The Expression Evaluator
Expression - PHP Class to safely evaluate math and boolean expressions
Copyright (C) 2005, Miles Kaufmann <http://www.twmagic.com/>
Copyright (C) 2016, Jakub Jankiewicz <http://jcubic.pl/>

### Description
Use the Expressoion class when you want to evaluate mathematical or boolean
expressions  from untrusted sources.  You can define your own variables and
functions, which are stored in the object.  Try it, it's fun!

Based on http://www.phpclasses.org/browse/file/11680.html, cred to Miles Kaufmann

## Features
### Methods
`$e->evalute($expr)`
	Evaluates the expression and returns the result.  If an error occurs,
	prints a warning and returns false.  If $expr is a function assignment,
	returns true on success.
`$e->e($expr)`
	A synonym for $e->evaluate().
`$e->vars()`
	Returns an associative array of all user-defined variables and values.
`$e->funcs()`
	Returns an array of all user-defined functions.

### Parameters
`$e->suppressErrors`
	Set to true to turn off warnings when evaluating expressions
`$e->lastError`
	If the last evaluation failed, contains a string describing the error.
	(Useful when suppressErrors is on).

## Usage
```
<?php
	use Espressioni\Expression;

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
	$e->addExternalFunction('foo', function($argument) {
		return "Your argument: $argument";
	});
	// and use it
	$result = $e->evaluate('foo("bar")');
?>
```
### For Testing
Download and unzip into the root of an Apache htdocs and call http://localhost/espressioni
The index.php lets you run the same example above.

## License
Custom License given by the original authors. See the LICENSE file for details.
