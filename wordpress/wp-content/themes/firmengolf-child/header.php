<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<?php /* Safari (iOS) färbt die Adressleisten-Umgebung sonst nach eigenem Ermessen
		ein (wirkte bräunlich); so trägt sie exakt unser Seiten-Papierweiß (--paper-100). */ ?>
	<meta name="theme-color" content="#FBFAF6">
	<?php wp_head(); ?>
</head>
<body <?php body_class(); ?>>
<?php wp_body_open(); ?>
