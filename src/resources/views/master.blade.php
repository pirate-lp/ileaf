<!DOCTYPE html>
<html lang="en">
	<head>
		
		<meta charset="utf-8" />
		<meta name="viewport" content="width=device-width, initial-scale=1.0">

		<title>@stack('title') | {{ config('app.name') }}</title>

		<meta name="description" content="@stack('description')">
				
	</head>

	<body>
	
		@yield('body')
		
	</body>
</html>