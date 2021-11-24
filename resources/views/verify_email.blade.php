<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <title>Document</title>
</head>
<body>
    
    @component('mail::message')
    <h1 style="text-align: center;"> SocailApp </h1>
    <h3 style="text-transform: capitalize;"> {{ $name }} - Please Confirm your SocialApp Account </h3>
    @component('mail::button', ['url' => $url])
    Confirm!
    @endcomponent  
    Thanks
    @endcomponent


</body>
</html>