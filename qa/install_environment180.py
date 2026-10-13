"""Reproducible cloud prerequisites; configuration and build logs stay private."""
import os,pathlib,socket,subprocess,urllib.parse
ENV=pathlib.Path('/workspace/fisitaap-env');ENV.mkdir(exist_ok=True)
dockerfile=ENV/'Dockerfile'
dockerfile.write_text('''FROM php:8.3-apache@sha256:f8506a19d79cad7fcf0c8049f1ed2208824961018fdc10bd80f324094dcd36bd
RUN apt-get update && apt-get install -y --no-install-recommends libwebp-dev libpng-dev libjpeg62-turbo-dev libfreetype6-dev libonig-dev libxml2-dev libcurl4-openssl-dev libzip-dev curl unzip && docker-php-ext-configure gd --with-freetype --with-jpeg --with-webp && docker-php-ext-install -j2 pdo_mysql mbstring gd dom curl zip exif && a2enmod rewrite headers expires deflate && rm -rf /var/lib/apt/lists/*
RUN printf 'display_errors=Off\\nlog_errors=On\\nupload_max_filesize=16M\\npost_max_size=24M\\n' > /usr/local/etc/php/conf.d/fisitaap.ini
''')
env=dict(os.environ,DOCKER_CONFIG=str(ENV/'docker'))
proxy=urllib.parse.urlparse(env.get('HTTP_PROXY',''))
args=['docker','build','--build-arg','HTTP_PROXY','--build-arg','HTTPS_PROXY']
if proxy.hostname:args+=['--add-host',proxy.hostname+':'+socket.gethostbyname(proxy.hostname)]
args+=['-t','fisitaap-dev:php83',str(ENV)]
with (ENV/'build.log').open('w') as output:
    result=subprocess.run(args,env=env,stdout=output,stderr=subprocess.STDOUT)
if result.returncode:raise SystemExit('PHP prerequisite build failed; inspect /workspace/fisitaap-env/build.log')
result=subprocess.run(['docker','pull','mariadb:11.4@sha256:1292844148b311e4ed4300022a996d39083f415a963e970cf47cad1b3b18e3a6'],env=env,capture_output=True)
if result.returncode:raise SystemExit('Verified MariaDB image pull failed')
print('PHP 8.3 / Apache with WebP and MariaDB prerequisites verified.')
