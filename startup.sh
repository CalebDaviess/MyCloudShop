#!/bin/sh
mkdir -p /home/site/data/cloudcart
chown -R www-data:www-data /home/site/data 2>/dev/null || chmod -R 777 /home/site/data 2>/dev/null || true

mkdir -p /html
cat <<'EOF' > /html/50x.html
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>CloudCart</title>
</head>
<body>
    <h1>CloudCart is refreshing</h1>
    <p><a href="/home.php">Return to the store</a></p>
</body>
</html>
EOF

for conf in /etc/nginx/sites-available/default /etc/nginx/sites-enabled/default; do
    if [ -f "$conf" ]; then
        sed -i 's|root /home/site/wwwroot/[^;]*;|root /home/site/wwwroot;|g' "$conf" 2>/dev/null || true
        sed -i 's|^\([[:space:]]*\)error_page 500 502 503 504 /50x.html;|\1# error_page 500 502 503 504 /50x.html;|g' "$conf" 2>/dev/null || true
    fi
done

service nginx reload 2>/dev/null || nginx -s reload 2>/dev/null || true
