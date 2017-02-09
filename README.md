PHP script CgBI形式のPNGを元のPNGに戻すスクリプト

# 修正内容

CgBI形式（ipaから取り出した画像ファイルなど）が上手くPHPで読み書き出来ないため元のPNGに再変換するコード。

オリジナルのコードだと上手く復元出来なかったので、修正版を書きました。

# オリジナル

[peperzaken/iPhone-optimized-PNG-reverse-script](https://github.com/peperzaken/iPhone-optimized-PNG-reverse-script)



### 使い方

	<?php
	// Include the class
	include 'Peperzaken/Ios/DecodeImage.php';
 
	// Initialize the class an set the source
	$processor = new Peperzaken_Ios_DecodeImage('football@2x.png');
 
	// Process the image en write it to this path
	$processor->decode('football@2x.regular.png');
	?>

We'l be adding more documentation to the code as we build it in to our project.
