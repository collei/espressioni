<?php
//
include 'src\Espressioni\Expression.php';
use Espressioni\Expression;
//
$expression = $_REQUEST['expression'] ?? '';
//
?>
<!doctype html>
<html>
<head>
	<style>
#divided {
	white-space: nowrap !important;
	width: 97.5%;
}
#divided fieldset {
	vertical-align: top !important;
	display: inline-block !important;
	/*height: 160px;*/
	margin: 0;
}
#divided fieldset.s20 {
	min-width: 17.5% !important;
	max-width: 17.5% !important;
}
#divided fieldset.s40 {
	min-width: 40% !important;
	max-width: 40% !important;
}
#logbelow textarea {
	width: 97vw !important;
	height: 57vh !important;
}
.autosiz {
	overflow-x: scroll !important;
	overflow-y: scroll !important;
}
	</style>
	<script>
function showside(sel)
{
	let pd = sel.options[sel.selectedIndex].getAttribute('datapack');
	let display = document.getElementById('showsider');
	display.innerHTML = pd;
}
	</script>
</head>
<body>
<hr>
<div id="divided">
	<fieldset>
		<form action="./" method="post">
			<p>
				<label for="url">Expression</label><br>
				<input type="text" id="expression" name="expression" value="" size="120" />
			</p>
			<p>
				<input type="submit" value="DO IT">
			</p>
		</form>
	</fieldset>
</div>
<hr>
<div id="logbelow" class="autosiz">
	<textarea class="autosiz">
<?php
//
################################################################
####	my own practice workspace, also serves as example	####
################################################################

if ($expression) {
	$e = new Expression();
	// basic evaluation:
	$result = $e->evaluate($expression);
	echo "\r\n$expression ----> " . $result;
	// basic evaluation:
	$result = $e->evaluate('2+2');
	echo "\r\n".'2+2 ----> ' . $result;
	// supports: order of operation; parentheses; negation; built-in functions
	$result = $e->evaluate('-8(5/2)^2*(1-sqrt(4))-8');
	echo "\r\n".'-8(5/2)^2*(1-sqrt(4))-8 ----> ' . $result;
	// support of booleans
	$result = $e->evaluate('10 < 20 || 20 > 30 && 10 == 10');
	echo "\r\n".'10 < 20 || 20 > 30 && 10 == 10 ----> ' . $result;
	// support for strings and match (regexes can be like in php or like in javascript)
	$result = $e->evaluate('"Foo,Bar" =~ /^([fo]+),(bar)$/i');
	echo "\r\nFoo,Bar =~ /^([fo]+),(bar)\$/i ----> " . $result;
	// previous call will create $0 for whole match match and $1,$2 for groups
	$result = $e->evaluate('$2');
	echo "\r\n".'$2 ----> ' . $result;
	// create your own variables
	$e->evaluate('a = e^(ln(pi))');
	echo "\r\n".'a = e^(ln(pi)) ----> ' . $result;
	// or functions
	$e->evaluate('f(x,y) = x^2 + y^2 - 2x*y + 1');
	echo "\r\n".'f(x,y) = x^2 + y^2 - 2x*y + 1 ----> ' . $result;
	// and then use them
	$result = $e->evaluate('3*f(42,a)');
	echo "\r\n".'3*f(42,a) ----> ' . $result;
	// create external functions
	$e->addExternalFunction('foo', function($cinco) {
		return "Cântebârrwlry á $cinco dám";
	});
	// and use it
	$result = $e->evaluate('foo(a)');
	//
	echo "\r\n".'foo() ----> ' . $result;
} else {
	echo 'Give a valid Expression to work with.';
}
//
?>
	</textarea>
</div>
</body>
</html>
