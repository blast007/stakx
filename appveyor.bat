cd c:\tools\php*
copy php.ini-production php.ini /Y
echo date.timezone="UTC" >> php.ini
echo extension_dir=ext >> php.ini
echo extension=php_openssl.dll >> php.ini
echo extension=php_mbstring.dll >> php.ini
echo extension=php_fileinfo.dll >> php.ini