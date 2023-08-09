<?php 
	$num_chars=6;//number of characters for captcha image
	$characters=array_merge(range(0,9),range('A','Z'),range('a','z'));//creating combination of numbers & alphabets
	shuffle($characters);//shuffling the characters
	$captcha_text="";
	for($i=0;$i<$num_chars;$i++)
	{
		$captcha_text.=$characters[rand(0,count($characters)-1)];
	}
?>
<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title>Document</title>
</head>
<body>
<img src="captcha.php?captch=<?=$captcha_text?>">
</body>
</html>