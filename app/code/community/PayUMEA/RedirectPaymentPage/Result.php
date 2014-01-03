<h1>PayU Response</h1>
<p>This page is to test results returned from PayU&trade;. You have to customise to redirect where required.
<br>Click <a href="/index.php">here</a> to return to Magento.</p>
<hr>

<?php
	if($_POST)
	{
		print('<h2>Values Posted from Safeshop.</h2>');
		print('<pre>');
		print_r($_POST);
		print('</pre>');
	}
	else
	{
		print('Nothing posted from Safeshop.Click <a href="http://secure.safeshop.co.za/documentation/" target="_blank">here</a> to view Safeshop developer documentation.');
	}
?>

<hr>


