<?php
/* =========================================================================
   LUMÉA — Dynamic E-Commerce Website  (Cosmetics & Skincare)
   Advanced Web Development in PHP — Assignment No. 2
   -------------------------------------------------------------------------
   SINGLE FILE APPLICATION.  Save as  index.php  inside  htdocs/lumea/
   Start Apache + MySQL (XAMPP) and open:  http://localhost/lumea/
   The database, tables, admin account and demo products are created
   AUTOMATICALLY on the first run.
   -------------------------------------------------------------------------
   Customer login : demo@lumea.com / demo123   (or register a new account)
   Admin login    : http://localhost/lumea/?page=admin_login
                    username: admin    password: admin123
   -------------------------------------------------------------------------
   IMAGES: every product, category and banner image is drawn as vector SVG
   inside this file and inlined as a data URI. Nothing is fetched from an
   image host, so images can never fail to load - the site looks identical
   offline. Admins can still override any image with an uploaded file or a
   URL from the admin panel; those are used as-is.
   ========================================================================= */

ob_start();                 // lets redirect() work even after markup has been echoed
session_start();
date_default_timezone_set('Asia/Kolkata');

/* Graceful fallback if the mbstring extension is not enabled on the server */
if (function_exists('mb_internal_encoding')) {
    mb_internal_encoding('UTF-8');
} else {
    function mb_substr($s, $start, $len = null) { return $len === null ? substr($s, $start) : substr($s, $start, $len); }
    function mb_strlen($s) { return strlen($s); }
}

/* ============================ 1. CONFIGURATION ========================== */
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');          // XAMPP default is empty
define('DB_NAME', 'lumea_shop');

define('SITE_NAME',    'LUMÉA');
define('SITE_TAG',     'Clean beauty, honestly made');
define('CURRENCY',     '₹');
define('FREE_SHIP_ABOVE', 999);
define('SHIP_FEE',     79);
define('TAX_PERCENT',  5);
define('UPLOAD_DIR',   'uploads');

/* ============================ 2. DATABASE ============================== */
function db(): PDO {
    static $pdo = null;
    if ($pdo !== null) return $pdo;
    try {
        $pdo = new PDO('mysql:host=' . DB_HOST . ';charset=utf8mb4', DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]);
        $pdo->exec('CREATE DATABASE IF NOT EXISTS `' . DB_NAME . '` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
        $pdo->exec('USE `' . DB_NAME . '`');
    } catch (PDOException $e) {
        die('<div style="font-family:system-ui;max-width:640px;margin:80px auto;padding:28px;border:1px solid #e3d9cd;border-radius:12px;background:#fff">
             <h2 style="margin-top:0">Database connection failed</h2>
             <p>Please start <b>MySQL</b> in XAMPP, then reload this page.</p>
             <p style="color:#8a7f70;font-size:13px">' . htmlspecialchars($e->getMessage()) . '</p></div>');
    }
    return $pdo;
}

function install_schema(): void {
    $db = db();
    $db->exec("CREATE TABLE IF NOT EXISTS admins (
        id INT AUTO_INCREMENT PRIMARY KEY,
        username VARCHAR(60) UNIQUE NOT NULL,
        password VARCHAR(255) NOT NULL,
        full_name VARCHAR(100) NOT NULL DEFAULT 'Administrator',
        created_at DATETIME NOT NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $db->exec("CREATE TABLE IF NOT EXISTS users (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(100) NOT NULL,
        email VARCHAR(120) UNIQUE NOT NULL,
        mobile VARCHAR(20) NOT NULL,
        password VARCHAR(255) NOT NULL,
        address VARCHAR(255) DEFAULT '',
        city VARCHAR(80) DEFAULT '',
        state VARCHAR(80) DEFAULT '',
        pincode VARCHAR(12) DEFAULT '',
        status TINYINT(1) NOT NULL DEFAULT 1,
        created_at DATETIME NOT NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $db->exec("CREATE TABLE IF NOT EXISTS categories (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(100) NOT NULL,
        slug VARCHAR(120) NOT NULL,
        image VARCHAR(400) DEFAULT '',
        description TEXT,
        status TINYINT(1) NOT NULL DEFAULT 1,
        created_at DATETIME NOT NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $db->exec("CREATE TABLE IF NOT EXISTS products (
        id INT AUTO_INCREMENT PRIMARY KEY,
        category_id INT NOT NULL,
        name VARCHAR(160) NOT NULL,
        image VARCHAR(400) DEFAULT '',
        price DECIMAL(10,2) NOT NULL DEFAULT 0,
        discount DECIMAL(10,2) NOT NULL DEFAULT 0,
        short_desc VARCHAR(300) DEFAULT '',
        description TEXT,
        specs TEXT,
        stock INT NOT NULL DEFAULT 0,
        rating DECIMAL(2,1) NOT NULL DEFAULT 4.5,
        is_featured TINYINT(1) NOT NULL DEFAULT 0,
        status TINYINT(1) NOT NULL DEFAULT 1,
        created_at DATETIME NOT NULL,
        INDEX (category_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $db->exec("CREATE TABLE IF NOT EXISTS orders (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        customer_name VARCHAR(100) NOT NULL,
        email VARCHAR(120) NOT NULL,
        mobile VARCHAR(20) NOT NULL,
        billing_address VARCHAR(255) NOT NULL,
        shipping_address VARCHAR(255) NOT NULL,
        city VARCHAR(80) NOT NULL,
        state VARCHAR(80) NOT NULL,
        pincode VARCHAR(12) NOT NULL,
        subtotal DECIMAL(10,2) NOT NULL DEFAULT 0,
        shipping DECIMAL(10,2) NOT NULL DEFAULT 0,
        tax DECIMAL(10,2) NOT NULL DEFAULT 0,
        total DECIMAL(10,2) NOT NULL DEFAULT 0,
        payment_method VARCHAR(40) NOT NULL,
        payment_status VARCHAR(20) NOT NULL DEFAULT 'Pending',
        order_status VARCHAR(20) NOT NULL DEFAULT 'Pending',
        note VARCHAR(255) DEFAULT '',
        created_at DATETIME NOT NULL,
        INDEX (user_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $db->exec("CREATE TABLE IF NOT EXISTS order_items (
        id INT AUTO_INCREMENT PRIMARY KEY,
        order_id INT NOT NULL,
        product_id INT NOT NULL,
        product_name VARCHAR(160) NOT NULL,
        product_image VARCHAR(400) DEFAULT '',
        price DECIMAL(10,2) NOT NULL,
        qty INT NOT NULL,
        line_total DECIMAL(10,2) NOT NULL,
        INDEX (order_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $db->exec("CREATE TABLE IF NOT EXISTS contacts (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(100) NOT NULL,
        email VARCHAR(120) NOT NULL,
        subject VARCHAR(160) DEFAULT '',
        message TEXT NOT NULL,
        is_read TINYINT(1) NOT NULL DEFAULT 0,
        created_at DATETIME NOT NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    /* ---- NEW: coupon engine ---- */
    $db->exec("CREATE TABLE IF NOT EXISTS coupons (
        id INT AUTO_INCREMENT PRIMARY KEY,
        code VARCHAR(40) UNIQUE NOT NULL,
        title VARCHAR(140) NOT NULL DEFAULT '',
        type VARCHAR(10) NOT NULL DEFAULT 'percent',
        value DECIMAL(10,2) NOT NULL DEFAULT 0,
        min_order DECIMAL(10,2) NOT NULL DEFAULT 0,
        max_discount DECIMAL(10,2) NOT NULL DEFAULT 0,
        usage_limit INT NOT NULL DEFAULT 0,
        per_user_limit INT NOT NULL DEFAULT 0,
        used_count INT NOT NULL DEFAULT 0,
        starts_at DATE NULL,
        expires_at DATE NULL,
        is_public TINYINT(1) NOT NULL DEFAULT 1,
        status TINYINT(1) NOT NULL DEFAULT 1,
        created_at DATETIME NOT NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $db->exec("CREATE TABLE IF NOT EXISTS coupon_uses (
        id INT AUTO_INCREMENT PRIMARY KEY,
        coupon_id INT NOT NULL,
        user_id INT NOT NULL,
        order_id INT NOT NULL,
        amount DECIMAL(10,2) NOT NULL DEFAULT 0,
        created_at DATETIME NOT NULL,
        INDEX (coupon_id), INDEX (user_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    migrate_columns();
    seed_data();
    seed_coupons();
    migrate_images();
}

/* Adds columns to tables that were created by an older version of this file,
   so an existing lumea_shop database keeps working without being dropped. */
function column_exists(string $table, string $col): bool {
    $st = db()->prepare('SELECT COUNT(*) FROM information_schema.COLUMNS
                         WHERE TABLE_SCHEMA=? AND TABLE_NAME=? AND COLUMN_NAME=?');
    $st->execute([DB_NAME, $table, $col]);
    return (int)$st->fetchColumn() > 0;
}
function migrate_columns(): void {
    if (!column_exists('orders', 'discount')) {
        db()->exec("ALTER TABLE orders ADD COLUMN discount DECIMAL(10,2) NOT NULL DEFAULT 0 AFTER shipping");
    }
    if (!column_exists('orders', 'coupon_code')) {
        db()->exec("ALTER TABLE orders ADD COLUMN coupon_code VARCHAR(40) NOT NULL DEFAULT '' AFTER discount");
    }
}

/* Starter coupons, created once. Admin can add/edit/remove them later. */
function seed_coupons(): void {
    $db = db();
    if ((int)$db->query('SELECT COUNT(*) FROM coupons')->fetchColumn() > 0) return;
    $now = date('Y-m-d H:i:s');
    $exp = date('Y-m-d', strtotime('+90 day'));
    $rows = [
        /* code, title, type, value, min_order, max_discount, usage_limit, per_user_limit, public */
        ['WELCOME10', 'Welcome offer — 10% off your first bag',      'percent', 10, 499,  200, 0, 1, 1],
        ['GLOW20',    'Glow week — 20% off orders above 1999',       'percent', 20, 1999, 500, 0, 0, 1],
        ['FLAT150',   'Flat 150 off orders above 1299',              'flat',    150, 1299, 0,  0, 0, 1],
        ['FREESHIP',  'Free delivery, no minimum',                   'ship',    0,   0,    0,  0, 0, 1],
        ['LUMEA500',  'Big basket — 500 off orders above 3499',      'flat',    500, 3499, 0,  0, 0, 1],
    ];
    $st = $db->prepare('INSERT INTO coupons
        (code,title,type,value,min_order,max_discount,usage_limit,per_user_limit,used_count,starts_at,expires_at,is_public,status,created_at)
        VALUES (?,?,?,?,?,?,?,?,0,NULL,?,?,1,?)');
    foreach ($rows as $r) {
        $st->execute([$r[0], $r[1], $r[2], $r[3], $r[4], $r[5], $r[6], $r[7], $exp, $r[8], $now]);
    }
}

function seed_data(): void {
    $db  = db();
    $now = date('Y-m-d H:i:s');

    if ((int)$db->query('SELECT COUNT(*) FROM admins')->fetchColumn() === 0) {
        $st = $db->prepare('INSERT INTO admins (username,password,full_name,created_at) VALUES (?,?,?,?)');
        $st->execute(['admin', password_hash('admin123', PASSWORD_DEFAULT), 'Store Administrator', $now]);
    }

    if ((int)$db->query('SELECT COUNT(*) FROM users')->fetchColumn() === 0) {
        $st = $db->prepare('INSERT INTO users (name,email,mobile,password,address,city,state,pincode,status,created_at)
                            VALUES (?,?,?,?,?,?,?,?,1,?)');
        $st->execute(['Demo Customer', 'demo@lumea.com', '9876543210',
            password_hash('demo123', PASSWORD_DEFAULT),
            '14 Palm Residency, CG Road', 'Ahmedabad', 'Gujarat', '380009', $now]);
    }

    if ((int)$db->query('SELECT COUNT(*) FROM categories')->fetchColumn() === 0) {
        $cats = [
            ['Skincare',  'skincare',  '', 'Serums, moisturisers and cleansers formulated for everyday skin barriers.', photo_url('skincare')],
            ['Makeup',    'makeup',    '', 'Buildable colour with clean pigments and a skin-first finish.',            photo_url('makeup')],
            ['Haircare',  'haircare',  '', 'Gentle, sulphate-free washes and treatments for scalp and length.',         photo_url('haircare')],
            ['Fragrance', 'fragrance', '', 'Layerable eau de parfums built around single, honest notes.',               photo_url('fragrance')],
        ];
        $st = $db->prepare('INSERT INTO categories (name,slug,image,description,status,created_at) VALUES (?,?,?,?,1,?)');
        foreach ($cats as $i => $c) {
            $st->execute([$c[0], $c[1], $c[4], $c[3], $now]);
        }
    }

    if ((int)$db->query('SELECT COUNT(*) FROM products')->fetchColumn() === 0) {
        $map = [];
        foreach ($db->query('SELECT id,slug FROM categories') as $r) $map[$r['slug']] = (int)$r['id'];

        $items = [
            ['skincare','Niacinamide 10% Clarifying Serum',photo_url('skincare'),899,649,'Fades marks and balances oil in 6 weeks.','A lightweight water-gel serum with 10% niacinamide and 1% zinc PCA. It visibly reduces post-acne marks, refines the look of pores and keeps midday shine under control without stripping the skin barrier.','Volume: 30 ml | Key actives: Niacinamide 10%, Zinc PCA 1% | Skin type: All, especially oily | pH: 5.5 | Fragrance-free',40,4.7,1],
            ['skincare','Hyaluronic Hydra Boost Essence',photo_url('skincare'),1099,849,'Five molecular weights of hyaluronic acid.','A bouncy essence layering five weights of hyaluronic acid with panthenol and betaine, so water is held at every depth of the skin instead of only on the surface.','Volume: 50 ml | Key actives: HA complex, Panthenol 2% | Skin type: Dry, dehydrated | Texture: Watery gel',35,4.8,1],
            ['skincare','Ceramide Barrier Night Cream',photo_url('skincare'),1299,999,'Rich overnight repair with 3 ceramides.','An overnight cream built on a ceramide NP, AP and EOP blend with cholesterol and squalane to rebuild a compromised barrier while you sleep.','Volume: 50 g | Key actives: Ceramide complex, Squalane | Skin type: Dry, sensitive | Use: PM',28,4.6,0],
            ['skincare','Gentle Amino Foaming Cleanser',photo_url('skincare'),699,0,'Soap-free wash that respects your pH.','An amino-acid based cleanser that removes sunscreen and grime without the tight, squeaky feel of a traditional foaming face wash.','Volume: 120 ml | Surfactant: Amino acid based | pH: 5.5 | Skin type: All',60,4.5,0],

            ['makeup','Velvet Matte Lipstick — Clay',photo_url('makeup'),749,599,'Eight hours of comfortable matte colour.','A soft-focus matte bullet in a warm terracotta clay tone. Pigment sits evenly on the lip with murumuru butter so the finish stays matte without cracking.','Shade: Clay (warm terracotta) | Finish: Velvet matte | Net: 3.8 g | Transfer resistant: Yes',45,4.6,1],
            ['makeup','Skin Tint SPF 30 Serum Foundation',photo_url('makeup'),1499,1199,'Second-skin coverage with real sun care.','A breathable serum foundation offering sheer-to-medium buildable coverage plus broad-spectrum SPF 30, so a base layer and sunscreen become one step.','Shades: 16 | SPF: 30 PA+++ | Coverage: Sheer to medium | Net: 30 ml',22,4.4,1],
            ['makeup','Feather Brow Precision Pencil',photo_url('makeup'),549,449,'1.5 mm tip for hair-like strokes.','An ultra-fine retractable pencil with a spoolie end, made to draw individual hair strokes rather than block colour into the brow.','Tip: 1.5 mm | Shades: 4 | Waterproof: Yes | Net: 0.09 g',70,4.3,0],
            ['makeup','Cloud Blush Cream Compact',photo_url('makeup'),649,0,'Cream-to-powder flush that melts in.','A whipped cream blush that sets to a soft powder finish, buildable from a wash of colour to a full sunset flush.','Shades: 6 | Finish: Natural dewy | Net: 5 g | Vegan: Yes',38,4.5,0],

            ['haircare','Rosemary Scalp Renew Shampoo',photo_url('haircare'),799,629,'Sulphate-free wash for a calmer scalp.','A clarifying yet gentle shampoo with rosemary leaf extract and caffeine that lifts buildup from the scalp without stripping colour-treated lengths.','Volume: 250 ml | Sulphate-free | Silicone-free | Hair type: Oily scalp, all lengths',50,4.6,1],
            ['haircare','Bond Repair Hair Mask',photo_url('haircare'),1199,949,'Rebuilds broken bonds in 8 minutes.','An intensive weekly mask using a bond-building complex with hydrolysed keratin to reduce breakage caused by heat, bleach and hard water.','Volume: 200 g | Use: 1–2× weekly | Hair type: Damaged, chemically treated',30,4.7,0],

            ['fragrance','Amber Dusk Eau de Parfum',photo_url('fragrance'),2499,1999,'Amber, vanilla and a smoked cedar base.','A warm evening scent opening on candied bergamot, settling into amber and vanilla, and drying down over smoked cedar with 8+ hour wear.','Volume: 50 ml | Family: Amber woody | Longevity: 8+ hrs | Sillage: Moderate',18,4.8,1],
            ['fragrance','Neroli Morning Eau de Parfum',photo_url('fragrance'),2299,0,'Bright neroli with green petitgrain.','A clean citrus floral that opens on neroli and petitgrain over a soft white musk base — light enough for daily wear in Indian summers.','Volume: 50 ml | Family: Citrus floral | Longevity: 5–6 hrs | Unisex: Yes',24,4.4,0],
        ];

        $st = $db->prepare('INSERT INTO products
            (category_id,name,image,price,discount,short_desc,description,specs,stock,rating,is_featured,status,created_at)
            VALUES (?,?,?,?,?,?,?,?,?,?,?,1,?)');
        foreach ($items as $i => $p) {
            if (!isset($map[$p[0]])) continue;
            $st->execute([
                $map[$p[0]], $p[1], $p[2], $p[3], $p[4], $p[5], $p[6], $p[7],
                $p[8], $p[9], $p[10], date('Y-m-d H:i:s', time() - ($i * 86400))
            ]);
        }
    }
}

/* ================= PRODUCT ARTWORK (self-contained, never 404s) =================
   Images are drawn as vector SVG and inlined as data URIs, so the site renders
   identically with or without an internet connection. Stored in the DB as a short
   token: art:{shape}:{palette}. Admin-uploaded files and URLs still work as-is. */
function art_palette(string $key): array {
    $p = [
        'skincare'  => ['#FBEEE5','#F2DCCB','#E2B08A','#C98A5E','#6B4430','#FFF9F3','#B9603F'],
        'makeup'    => ['#F9E8E8','#EFD2D6','#C2637A','#A2465F','#3A2027','#FFF6F7','#A2465F'],
        'haircare'  => ['#E8F0EA','#D5E3D9','#7FA88C','#5B8A6C','#2C4436','#F7FBF8','#3E5C47'],
        'fragrance' => ['#FBF2DE','#F2E4C4','#E3BC72','#C79A45','#3C3020','#FFFBF0','#9C7F3F'],
        'neutral'   => ['#F4EFE5','#E6DDCC','#CBBDA6','#AD9C83','#4A4238','#FFFCF6','#8B7D74'],
    ];
    return $p[$key] ?? $p['neutral'];
}

function art_svg(string $shape, string $palKey): string {
    [$bg1,$bg2,$body,$bodyDk,$cap,$label,$accent] = art_palette($palKey);
    $id = substr(md5($shape.$palKey),0,6);

    $defs = '<defs>'
      . '<linearGradient id="bg'.$id.'" x1="0" y1="0" x2="1" y2="1">'
      . '<stop offset="0" stop-color="'.$bg1.'"/><stop offset="1" stop-color="'.$bg2.'"/></linearGradient>'
      . '<linearGradient id="bd'.$id.'" x1="0" y1="0" x2="1" y2="0">'
      . '<stop offset="0" stop-color="'.$bodyDk.'"/><stop offset=".42" stop-color="'.$body.'"/>'
      . '<stop offset=".62" stop-color="'.$body.'"/><stop offset="1" stop-color="'.$bodyDk.'"/></linearGradient>'
      . '<linearGradient id="cp'.$id.'" x1="0" y1="0" x2="1" y2="0">'
      . '<stop offset="0" stop-color="'.$cap.'"/><stop offset=".45" stop-color="'.$cap.'" stop-opacity=".82"/>'
      . '<stop offset="1" stop-color="'.$cap.'"/></linearGradient>'
      . '<radialGradient id="gl'.$id.'" cx=".5" cy=".42" r=".55">'
      . '<stop offset="0" stop-color="#FFFFFF" stop-opacity=".55"/><stop offset="1" stop-color="#FFFFFF" stop-opacity="0"/></radialGradient>'
      . '</defs>';

    // backdrop: gradient, halo, soft ground shadow
    $back = '<rect width="400" height="400" fill="url(#bg'.$id.')"/>'
      . '<circle cx="200" cy="178" r="118" fill="url(#gl'.$id.')"/>'
      . '<circle cx="200" cy="175" r="104" fill="none" stroke="'.$accent.'" stroke-opacity=".16" stroke-width="1.2"/>'
      . '<ellipse cx="200" cy="332" rx="86" ry="13" fill="'.$cap.'" opacity=".13"/>';

    // tiny brand tick, bottom-right
    $mark = '<circle cx="352" cy="352" r="13" fill="'.$accent.'" opacity=".14"/>'
      . '<path d="M346 352l4 4 8-9" stroke="'.$accent.'" stroke-width="2" fill="none" stroke-linecap="round" stroke-linejoin="round" opacity=".55"/>';

    $g = '';
    switch ($shape) {

    case 'dropper': // serum bottle with pipette cap
      $g = '<rect x="182" y="96" width="36" height="46" rx="5" fill="url(#cp'.$id.')"/>'
         . '<rect x="188" y="88" width="24" height="12" rx="4" fill="'.$cap.'"/>'
         . '<rect x="176" y="140" width="48" height="18" rx="5" fill="'.$bodyDk.'"/>'
         . '<path d="M158 172q0-14 18-20h48q18 6 18 20v128q0 14-14 14h-56q-14 0-14-14z" fill="url(#bd'.$id.')"/>'
         . '<rect x="172" y="196" width="56" height="70" rx="6" fill="'.$label.'" opacity=".93"/>'
         . '<rect x="182" y="212" width="36" height="4" rx="2" fill="'.$accent.'"/>'
         . '<rect x="182" y="224" width="26" height="3" rx="1.5" fill="'.$cap.'" opacity=".4"/>'
         . '<rect x="182" y="234" width="32" height="3" rx="1.5" fill="'.$cap.'" opacity=".28"/>'
         . '<rect x="182" y="248" width="18" height="6" rx="3" fill="'.$accent.'" opacity=".6"/>'
         . '<rect x="166" y="178" width="8" height="120" rx="4" fill="#FFF" opacity=".22"/>';
      break;

    case 'jar': // squat cream jar with lid
      $g = '<rect x="134" y="180" width="132" height="40" rx="12" fill="url(#cp'.$id.')"/>'
         . '<rect x="140" y="170" width="120" height="16" rx="8" fill="'.$cap.'" opacity=".85"/>'
         . '<path d="M142 218h116v72q0 16-16 16h-84q-16 0-16-16z" fill="url(#bd'.$id.')"/>'
         . '<rect x="160" y="240" width="80" height="44" rx="6" fill="'.$label.'" opacity=".93"/>'
         . '<rect x="172" y="254" width="42" height="4" rx="2" fill="'.$accent.'"/>'
         . '<rect x="172" y="266" width="56" height="3" rx="1.5" fill="'.$cap.'" opacity=".32"/>'
         . '<rect x="148" y="222" width="8" height="76" rx="4" fill="#FFF" opacity=".2"/>';
      break;

    case 'tube': // squeeze tube, crimped top
      $g = '<rect x="168" y="276" width="64" height="16" rx="4" fill="url(#cp'.$id.')"/>'
         . '<path d="M166 120h68l-8 156h-52z" fill="url(#bd'.$id.')"/>'
         . '<rect x="164" y="112" width="72" height="12" rx="4" fill="'.$cap.'" opacity=".75"/>'
         . '<rect x="176" y="156" width="48" height="76" rx="6" fill="'.$label.'" opacity=".93"/>'
         . '<rect x="186" y="172" width="30" height="4" rx="2" fill="'.$accent.'"/>'
         . '<rect x="186" y="184" width="22" height="3" rx="1.5" fill="'.$cap.'" opacity=".34"/>'
         . '<circle cx="200" cy="212" r="11" fill="none" stroke="'.$accent.'" stroke-width="2" opacity=".5"/>'
         . '<rect x="172" y="130" width="7" height="140" rx="3.5" fill="#FFF" opacity=".2"/>';
      break;

    case 'pump': // lotion / cleanser pump bottle
      $g = '<rect x="192" y="86" width="16" height="30" rx="4" fill="'.$cap.'"/>'
         . '<path d="M208 92h22q6 0 6 6v8" stroke="'.$cap.'" stroke-width="9" fill="none" stroke-linecap="round"/>'
         . '<rect x="180" y="114" width="40" height="26" rx="6" fill="url(#cp'.$id.')"/>'
         . '<path d="M152 168q0-16 20-28h56q20 12 20 28v122q0 16-16 16h-64q-16 0-16-16z" fill="url(#bd'.$id.')"/>'
         . '<rect x="168" y="196" width="64" height="74" rx="6" fill="'.$label.'" opacity=".93"/>'
         . '<rect x="180" y="214" width="40" height="4" rx="2" fill="'.$accent.'"/>'
         . '<rect x="180" y="226" width="30" height="3" rx="1.5" fill="'.$cap.'" opacity=".34"/>'
         . '<rect x="180" y="236" width="36" height="3" rx="1.5" fill="'.$cap.'" opacity=".26"/>'
         . '<rect x="162" y="174" width="8" height="122" rx="4" fill="#FFF" opacity=".2"/>';
      break;

    case 'lipstick': // bullet lipstick, cap off
      $g = '<path d="M180 150l40-16v66h-40z" fill="'.$accent.'"/>'
         . '<path d="M180 150l40-16v10l-40 16z" fill="#FFF" opacity=".3"/>'
         . '<rect x="176" y="198" width="48" height="26" rx="4" fill="'.$cap.'"/>'
         . '<path d="M174 224h52v76q0 12-12 12h-28q-12 0-12-12z" fill="url(#bd'.$id.')"/>'
         . '<rect x="186" y="246" width="28" height="42" rx="4" fill="'.$label.'" opacity=".9"/>'
         . '<rect x="193" y="258" width="14" height="3" rx="1.5" fill="'.$accent.'"/>'
         . '<rect x="193" y="268" width="10" height="2.5" rx="1.2" fill="'.$cap.'" opacity=".35"/>'
         . '<rect x="180" y="230" width="6" height="68" rx="3" fill="#FFF" opacity=".22"/>';
      break;

    case 'foundation': // tall slim bottle with pump
      $g = '<rect x="192" y="92" width="16" height="26" rx="4" fill="'.$cap.'"/>'
         . '<path d="M208 98h20q6 0 6 6v6" stroke="'.$cap.'" stroke-width="8" fill="none" stroke-linecap="round"/>'
         . '<rect x="182" y="116" width="36" height="22" rx="5" fill="url(#cp'.$id.')"/>'
         . '<rect x="164" y="138" width="72" height="162" rx="10" fill="url(#bd'.$id.')"/>'
         . '<rect x="176" y="172" width="48" height="88" rx="6" fill="'.$label.'" opacity=".93"/>'
         . '<rect x="186" y="188" width="30" height="4" rx="2" fill="'.$accent.'"/>'
         . '<rect x="186" y="200" width="22" height="3" rx="1.5" fill="'.$cap.'" opacity=".34"/>'
         . '<rect x="186" y="228" width="28" height="7" rx="3.5" fill="'.$accent.'" opacity=".55"/>'
         . '<rect x="172" y="150" width="7" height="138" rx="3.5" fill="#FFF" opacity=".2"/>';
      break;

    case 'pencil': // slim brow pencil, angled
      $g = '<g transform="rotate(-18 200 210)">'
         . '<path d="M188 118l24 0 6 22-36 0z" fill="'.$accent.'"/>'
         . '<path d="M182 140h36v122h-36z" fill="url(#bd'.$id.')"/>'
         . '<rect x="182" y="262" width="36" height="14" fill="'.$cap.'" opacity=".8"/>'
         . '<path d="M182 276h36v34q0 10-10 10h-16q-10 0-10-10z" fill="'.$cap.'"/>'
         . '<rect x="190" y="168" width="20" height="60" rx="4" fill="'.$label.'" opacity=".9"/>'
         . '<rect x="195" y="182" width="10" height="3" rx="1.5" fill="'.$accent.'"/>'
         . '<rect x="186" y="146" width="5" height="108" rx="2.5" fill="#FFF" opacity=".22"/>'
         . '</g>';
      break;

    case 'compact': // round compact, open
      $g = '<circle cx="200" cy="218" r="76" fill="url(#bd'.$id.')"/>'
         . '<circle cx="200" cy="218" r="62" fill="'.$cap.'" opacity=".18"/>'
         . '<circle cx="200" cy="218" r="50" fill="'.$accent.'" opacity=".85"/>'
         . '<circle cx="182" cy="202" r="18" fill="#FFF" opacity=".22"/>'
         . '<path d="M124 218a76 76 0 0 1 152 0" fill="none" stroke="#FFF" stroke-opacity=".28" stroke-width="3"/>'
         . '<rect x="188" y="134" width="24" height="10" rx="5" fill="'.$cap.'" opacity=".6"/>';
      break;

    case 'shampoo': // tall bottle, flip cap
      $g = '<rect x="174" y="98" width="52" height="30" rx="7" fill="url(#cp'.$id.')"/>'
         . '<rect x="186" y="90" width="28" height="12" rx="5" fill="'.$cap.'" opacity=".8"/>'
         . '<path d="M158 150q0-18 22-24h40q22 6 22 24v142q0 14-14 14h-56q-14 0-14-14z" fill="url(#bd'.$id.')"/>'
         . '<rect x="170" y="184" width="60" height="86" rx="6" fill="'.$label.'" opacity=".93"/>'
         . '<rect x="182" y="202" width="36" height="4" rx="2" fill="'.$accent.'"/>'
         . '<rect x="182" y="214" width="26" height="3" rx="1.5" fill="'.$cap.'" opacity=".34"/>'
         . '<rect x="182" y="224" width="32" height="3" rx="1.5" fill="'.$cap.'" opacity=".26"/>'
         . '<path d="M188 244q6-10 12 0t12 0" stroke="'.$accent.'" stroke-width="2.5" fill="none" opacity=".6" stroke-linecap="round"/>'
         . '<rect x="166" y="160" width="8" height="132" rx="4" fill="#FFF" opacity=".2"/>';
      break;

    case 'maskjar': // wide treatment jar
      $g = '<rect x="126" y="172" width="148" height="44" rx="14" fill="url(#cp'.$id.')"/>'
         . '<rect x="136" y="162" width="128" height="16" rx="8" fill="'.$cap.'" opacity=".82"/>'
         . '<path d="M134 214h132v66q0 20-20 20h-92q-20 0-20-20z" fill="url(#bd'.$id.')"/>'
         . '<rect x="156" y="234" width="88" height="48" rx="6" fill="'.$label.'" opacity=".93"/>'
         . '<rect x="170" y="248" width="48" height="4" rx="2" fill="'.$accent.'"/>'
         . '<rect x="170" y="260" width="60" height="3" rx="1.5" fill="'.$cap.'" opacity=".32"/>'
         . '<rect x="142" y="218" width="8" height="72" rx="4" fill="#FFF" opacity=".2"/>';
      break;

    case 'flacon': // perfume bottle, heavy cap
      $g = '<rect x="180" y="110" width="40" height="42" rx="4" fill="url(#cp'.$id.')"/>'
         . '<rect x="186" y="152" width="28" height="14" rx="3" fill="'.$cap.'" opacity=".7"/>'
         . '<path d="M156 196q0-22 24-30h40q24 8 24 30v86q0 20-20 20h-48q-20 0-20-20z" fill="url(#bd'.$id.')"/>'
         . '<rect x="172" y="216" width="56" height="60" rx="5" fill="'.$label.'" opacity=".9"/>'
         . '<rect x="186" y="234" width="28" height="4" rx="2" fill="'.$accent.'"/>'
         . '<rect x="182" y="246" width="36" height="3" rx="1.5" fill="'.$cap.'" opacity=".34"/>'
         . '<path d="M192 258h16" stroke="'.$accent.'" stroke-width="2" opacity=".5" stroke-linecap="round"/>'
         . '<rect x="164" y="204" width="8" height="90" rx="4" fill="#FFF" opacity=".24"/>';
      break;

    default: // generic carton
      $g = '<path d="M150 160h100v140q0 12-12 12h-76q-12 0-12-12z" fill="url(#bd'.$id.')"/>'
         . '<rect x="150" y="146" width="100" height="18" rx="5" fill="url(#cp'.$id.')"/>'
         . '<rect x="168" y="196" width="64" height="66" rx="6" fill="'.$label.'" opacity=".93"/>'
         . '<rect x="180" y="214" width="40" height="4" rx="2" fill="'.$accent.'"/>'
         . '<rect x="180" y="226" width="28" height="3" rx="1.5" fill="'.$cap.'" opacity=".32"/>';
    }

    return '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 400 400" width="400" height="400">'
         . $defs . $back . $g . $mark . '</svg>';
}

/* ================= PRODUCT PHOTOGRAPHY (real photos, vector-art safety net) =================
   Product and category images are real photographs served from Unsplash's image CDN
   (images.unsplash.com) - a large, production-grade CDN, not a small keyword-redirector
   service. Each category has one fixed, permanent photo ID, so the same image loads
   every time (no randomness, no rate limiting). If a photo ever fails to load for any
   reason (no internet, a network hiccup, a blocked domain on a locked-down network),
   every <img> tag falls back automatically - via onerror - to a hand-drawn vector
   illustration generated inline in this file, so the site NEVER shows a broken image
   icon under any circumstances. Admins can still override any image with an uploaded
   file or their own URL from the admin panel; those are used as-is with the same
   automatic fallback safety net. */

const PHOTO_LIBRARY = [
    'skincare'  => '1741896135490-4062a3b21abf', // skincare serums & dropper bottles
    'makeup'    => '1631730486572-226d1f595b68', // assorted makeup products
    'haircare'  => '1701992678972-d5a053ad0fb0', // shampoo / haircare bottle
    'fragrance' => '1553699357-fdefb876c402',    // eau de parfum bottle
];

/** Build a right-sized, compressed Unsplash CDN URL for a given palette/category. */
function photo_url(string $pal, int $w = 800, int $h = 800): string {
    $id = PHOTO_LIBRARY[$pal] ?? PHOTO_LIBRARY['skincare'];
    return "https://images.unsplash.com/photo-{$id}?w={$w}&h={$h}&fit=crop&crop=entropy&auto=format&q=75";
}

/** Guess a bottle/product shape from its name — most specific terms first. */
function guess_shape(string $name): string {
    $n = strtolower($name);
    $byName = ['lipstick'=>'lipstick','blush'=>'compact','compact'=>'compact','pencil'=>'pencil',
               'brow'=>'pencil','tint'=>'foundation','foundation'=>'foundation','concealer'=>'foundation',
               'mask'=>'maskjar','shampoo'=>'shampoo','conditioner'=>'shampoo',
               'parfum'=>'flacon','perfume'=>'flacon','fragrance'=>'flacon',
               'cleanser'=>'tube','face wash'=>'tube','wash'=>'tube',
               'essence'=>'dropper','serum'=>'dropper','oil'=>'dropper',
               'cream'=>'jar','moisturis'=>'jar','balm'=>'jar','butter'=>'jar'];
    foreach ($byName as $k => $v) { if (strpos($n, $k) !== false) return $v; }
    return 'box';
}
/** Map a shape to its default colour palette / photo category. */
function shape_pal(string $shape): string {
    $map = ['dropper'=>'skincare','jar'=>'skincare','tube'=>'skincare','pump'=>'skincare',
            'lipstick'=>'makeup','foundation'=>'makeup','pencil'=>'makeup','compact'=>'makeup',
            'shampoo'=>'haircare','maskjar'=>'haircare','flacon'=>'fragrance'];
    return $map[$shape] ?? 'neutral';
}
/** Resolve a category slug (skincare/makeup/haircare/fragrance) straight to its palette. */
function slug_pal(string $slug): string {
    return in_array($slug, ['skincare','makeup','haircare','fragrance'], true) ? $slug : 'neutral';
}

function art_uri(string $shape, string $pal): string {
    return 'data:image/svg+xml;charset=utf-8,' . rawurlencode(art_svg($shape, $pal));
}

/** The vector-art fallback URL for a given product/category name + optional slug. */
function art_fallback(string $name, string $slug = ''): string {
    $shape = guess_shape($name);
    $pal   = $slug !== '' ? slug_pal($slug) : shape_pal($shape);
    return art_uri($shape, $pal);
}

/** Resolve any stored image value into something an <img src> can always render. */
function img_src($v, string $name = '', string $slug = ''): string {
    $v = trim((string)$v);
    if ($v === '')                       return art_fallback($name, $slug);
    if (strncmp($v, 'art:', 4) === 0) {  // legacy token from an older install
        $p = explode(':', $v);
        return art_uri($p[1] ?? guess_shape($name), $p[2] ?? shape_pal($p[1] ?? ''));
    }
    return $v;                           // real photo URL, or an admin-uploaded file path
}

/** Wide abstract backdrop for the home page hero slides (art-drawn, always available). */
function hero_art(int $i): string {
    $sets = [['#241C19','#4A2C1E','#B9603F','#E3A277'],
             ['#241C19','#43222C','#A2465F','#D98AA0'],
             ['#241C19','#443318','#9C7F3F','#E3C179']];
    list($d1,$d2,$a1,$a2) = $sets[$i % 3];
    $s = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1600 900" width="1600" height="900">'
      . '<defs>'
      . '<linearGradient id="hb' . $i . '" x1="0" y1="0" x2="1" y2="1"><stop offset="0" stop-color="' . $d1 . '"/><stop offset="1" stop-color="' . $d2 . '"/></linearGradient>'
      . '<radialGradient id="h1' . $i . '" cx=".5" cy=".5" r=".5"><stop offset="0" stop-color="' . $a1 . '" stop-opacity=".85"/><stop offset="1" stop-color="' . $a1 . '" stop-opacity="0"/></radialGradient>'
      . '<radialGradient id="h2' . $i . '" cx=".5" cy=".5" r=".5"><stop offset="0" stop-color="' . $a2 . '" stop-opacity=".55"/><stop offset="1" stop-color="' . $a2 . '" stop-opacity="0"/></radialGradient>'
      . '</defs>'
      . '<rect width="1600" height="900" fill="url(#hb' . $i . ')"/>'
      . '<circle cx="1180" cy="330" r="420" fill="url(#h1' . $i . ')"/>'
      . '<circle cx="1420" cy="720" r="300" fill="url(#h2' . $i . ')"/>'
      . '<circle cx="300" cy="820" r="260" fill="url(#h2' . $i . ')" opacity=".5"/>'
      . '<g fill="none" stroke="#FFFFFF" stroke-opacity=".07" stroke-width="1.4">'
      . '<circle cx="1180" cy="330" r="250"/><circle cx="1180" cy="330" r="330"/><circle cx="1180" cy="330" r="410"/></g>'
      . '<g opacity=".16" fill="#FFFFFF"><rect x="1105" y="250" width="150" height="250" rx="34"/>'
      . '<rect x="1150" y="200" width="60" height="60" rx="12"/></g></svg>';
    return 'data:image/svg+xml;charset=utf-8,' . rawurlencode($s);
}

/** Hero slide background: a real photo (CSS applies a dark cinematic gradient over it). */
function hero_photo(int $i): string {
    $pals = ['skincare','makeup','fragrance'];
    return photo_url($pals[$i % 3], 1600, 900);
}

/** Three-up real-photo strip used on the About page. */
function collage_art(): string {
    return photo_url('skincare', 1200, 400);
}

/** One-time upgrade: swap any legacy/broken image for a real photo of the right category. */
function migrate_images(): void {
    $db = db();
    $rows = $db->query("SELECT p.id, p.name, c.slug FROM products p
                        LEFT JOIN categories c ON c.id = p.category_id
                        WHERE p.image LIKE '%loremflickr%' OR p.image LIKE 'art:%'
                           OR p.image IS NULL OR p.image = ''")->fetchAll();
    $up = $db->prepare('UPDATE products SET image=? WHERE id=?');
    foreach ($rows as $r) {
        $slug  = (string)($r['slug'] ?? '');
        $shape = guess_shape((string)$r['name']);
        $pal   = $slug !== '' ? slug_pal($slug) : shape_pal($shape);
        $up->execute([photo_url($pal), $r['id']]);
    }

    $cats = $db->query("SELECT id, slug FROM categories
                        WHERE image LIKE '%loremflickr%' OR image LIKE 'art:%'
                           OR image IS NULL OR image = ''")->fetchAll();
    $uc = $db->prepare('UPDATE categories SET image=? WHERE id=?');
    foreach ($cats as $c) {
        $uc->execute([photo_url(slug_pal((string)$c['slug'])), $c['id']]);
    }

    try {
        $db->exec("UPDATE order_items oi JOIN products p ON p.id = oi.product_id
                   SET oi.product_image = p.image
                   WHERE oi.product_image LIKE '%loremflickr%' OR oi.product_image LIKE 'art:%'
                      OR oi.product_image IS NULL OR oi.product_image = ''");
    } catch (Throwable $e) { /* historical rows only — safe to skip */ }
}

/* ============================ 3. HELPERS ============================== */
function e($v): string { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
function money($n): string { return CURRENCY . number_format((float)$n, 2); }
function url(array $p = []): string { return '?' . http_build_query($p); }
function redirect(string $u): void {
    while (ob_get_level() > 0) ob_end_clean();   // drop any markup already rendered
    header('Location: ' . $u);
    exit;
}
function post(string $k, $d = ''): string { return isset($_POST[$k]) ? trim((string)$_POST[$k]) : $d; }
function get(string $k, $d = '')  { return $_GET[$k] ?? $d; }

function flash(string $msg, string $type = 'success'): void {
    $_SESSION['flash'][] = ['m' => $msg, 't' => $type];
}
function take_flashes(): array {
    $f = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $f;
}

function csrf_token(): string {
    if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(16));
    return $_SESSION['csrf'];
}
function csrf_field(): string {
    return '<input type="hidden" name="csrf" value="' . e(csrf_token()) . '">';
}
function csrf_ok(): bool {
    return isset($_POST['csrf']) && hash_equals($_SESSION['csrf'] ?? '', (string)$_POST['csrf']);
}

function current_user(): ?array {
    if (empty($_SESSION['uid'])) return null;
    static $u = null;
    if ($u === null) {
        $st = db()->prepare('SELECT * FROM users WHERE id=? AND status=1');
        $st->execute([(int)$_SESSION['uid']]);
        $u = $st->fetch() ?: null;
        if (!$u) unset($_SESSION['uid']);
    }
    return $u ?: null;
}
function require_login(string $next = 'home'): void {
    if (!current_user()) {
        $_SESSION['after_login'] = $next;
        flash('Please log in to continue.', 'error');
        redirect(url(['page' => 'login']));
    }
}
function current_admin(): ?array {
    if (empty($_SESSION['aid'])) return null;
    $st = db()->prepare('SELECT * FROM admins WHERE id=?');
    $st->execute([(int)$_SESSION['aid']]);
    return $st->fetch() ?: null;
}
function require_admin(): void {
    if (!current_admin()) redirect(url(['page' => 'admin_login']));
}

function final_price(array $p): float {
    $d = (float)$p['discount'];
    return $d > 0 && $d < (float)$p['price'] ? $d : (float)$p['price'];
}
function discount_percent(array $p): int {
    $d = (float)$p['discount'];
    $pr = (float)$p['price'];
    if ($d <= 0 || $d >= $pr || $pr <= 0) return 0;
    return (int)round((($pr - $d) / $pr) * 100);
}
function slugify(string $s): string {
    $s = strtolower(trim(preg_replace('/[^A-Za-z0-9]+/', '-', $s), '-'));
    return $s !== '' ? $s : 'item';
}
function star_html(float $r): string {
    $full = (int)floor($r);
    $half = ($r - $full) >= 0.5;
    $out  = '';
    for ($i = 1; $i <= 5; $i++) {
        if ($i <= $full)      $out .= '<span class="st on">&#9733;</span>';
        elseif ($i === $full + 1 && $half) $out .= '<span class="st on">&#9733;</span>';
        else                  $out .= '<span class="st">&#9733;</span>';
    }
    return '<span class="stars">' . $out . '</span>';
}

/* --------------------------- CART (SESSION) --------------------------- */
function cart(): array { return $_SESSION['cart'] ?? []; }
function cart_set(array $c): void { $_SESSION['cart'] = $c; }
/* Stock-aware add. The bag can never hold more units than the warehouse has.
   Returns ['ok'=>bool, 'capped'=>bool, 'stock'=>int, 'msg'=>string]. */
function cart_add(int $pid, int $qty = 1): array {
    $st = db()->prepare('SELECT id,name,stock,status FROM products WHERE id=?');
    $st->execute([$pid]);
    $p = $st->fetch();
    if (!$p || (int)$p['status'] !== 1) {
        return ['ok' => false, 'capped' => false, 'stock' => 0, 'msg' => 'That product is not available any more.'];
    }
    $stock = (int)$p['stock'];
    if ($stock < 1) {
        return ['ok' => false, 'capped' => false, 'stock' => 0, 'msg' => '"' . $p['name'] . '" is out of stock.'];
    }
    $c    = cart();
    $have = (int)($c[$pid] ?? 0);
    $want = $have + max(1, $qty);

    if ($want > $stock) {
        $unit = $stock === 1 ? 'piece' : 'pieces';
        if ($have >= $stock) {
            return ['ok' => false, 'capped' => true, 'stock' => $stock,
                    'msg' => 'Only ' . $stock . ' ' . $unit . ' of "' . $p['name'] . '" in stock — your bag already has all of them.'];
        }
        $c[$pid] = $stock;
        cart_set($c);
        return ['ok' => true, 'capped' => true, 'stock' => $stock,
                'msg' => 'Only ' . $stock . ' ' . $unit . ' of "' . $p['name'] . '" left — quantity set to ' . $stock . '.'];
    }
    $c[$pid] = $want;
    cart_set($c);
    return ['ok' => true, 'capped' => false, 'stock' => $stock, 'msg' => $p['name'] . ' added to your bag.'];
}

/* Returns ['ok'=>bool, 'capped'=>bool, 'msg'=>string]; msg is '' when nothing to report. */
function cart_update(int $pid, int $qty): array {
    $c = cart();
    if ($qty <= 0) { unset($c[$pid]); cart_set($c); return ['ok' => true, 'capped' => false, 'msg' => '']; }

    $st = db()->prepare('SELECT name,stock,status FROM products WHERE id=?');
    $st->execute([$pid]);
    $p = $st->fetch();
    if (!$p || (int)$p['status'] !== 1) {
        unset($c[$pid]); cart_set($c);
        return ['ok' => false, 'capped' => false, 'msg' => 'An item was removed from your bag — it is no longer sold.'];
    }
    $stock = (int)$p['stock'];
    if ($stock < 1) {
        unset($c[$pid]); cart_set($c);
        return ['ok' => false, 'capped' => false, 'msg' => '"' . $p['name'] . '" went out of stock and was removed from your bag.'];
    }
    if ($qty > $stock) {
        $c[$pid] = $stock; cart_set($c);
        return ['ok' => false, 'capped' => true,
                'msg' => 'Only ' . $stock . ' ' . ($stock === 1 ? 'piece' : 'pieces') . ' of "' . $p['name'] . '" available — quantity set to ' . $stock . '.'];
    }
    $c[$pid] = $qty; cart_set($c);
    return ['ok' => true, 'capped' => false, 'msg' => ''];
}
function cart_remove(int $pid): void {
    $c = cart(); unset($c[$pid]); cart_set($c);
}
function cart_count(): int { return array_sum(cart()); }

function cart_detailed(): array {
    $c = cart();
    if (!$c) return [];
    $ids  = array_map('intval', array_keys($c));
    $in   = implode(',', array_fill(0, count($ids), '?'));
    $st   = db()->prepare("SELECT * FROM products WHERE id IN ($in) AND status=1");
    $st->execute($ids);
    $rows = [];
    foreach ($st->fetchAll() as $p) {
        $qty   = (int)$c[$p['id']];
        $price = final_price($p);
        $p['qty']        = $qty;
        $p['unit_price'] = $price;
        $p['line_total'] = $price * $qty;
        $p['stock_left'] = (int)$p['stock'];
        $p['over']       = $qty > (int)$p['stock'];
        $rows[] = $p;
    }
    return $rows;
}

/* Every line in the bag that asks for more than the shop actually has.
   Empty array means the bag is safe to check out. */
function cart_stock_issues(): array {
    $bad = [];
    foreach (cart_detailed() as $r) {
        if (!$r['over']) continue;
        $bad[] = $r['stock_left'] < 1
            ? '"' . $r['name'] . '" is out of stock — please remove it to continue.'
            : '"' . $r['name'] . '" — you asked for ' . $r['qty'] . ' but only ' . $r['stock_left']
              . ' ' . ($r['stock_left'] === 1 ? 'piece is' : 'pieces are') . ' available.';
    }
    return $bad;
}

/* --------------------------- COUPONS --------------------------- */
function coupon_find(string $code): ?array {
    $st = db()->prepare('SELECT * FROM coupons WHERE code=?');
    $st->execute([strtoupper(trim($code))]);
    return $st->fetch() ?: null;
}
function coupon_label(array $cp): string {
    if ($cp['type'] === 'ship') return 'Free shipping';
    if ($cp['type'] === 'percent') {
        $s = rtrim(rtrim(number_format((float)$cp['value'], 2, '.', ''), '0'), '.') . '% off';
        if ((float)$cp['max_discount'] > 0) $s .= ', up to ' . money($cp['max_discount']);
        return $s;
    }
    return money($cp['value']) . ' off';
}
/* Returns '' when the coupon can be used, otherwise a plain-English reason. */
function coupon_problem(array $cp, float $sub, ?int $uid): string {
    $today = date('Y-m-d');
    if ((int)$cp['status'] !== 1)                                 return 'That coupon is no longer active.';
    if (!empty($cp['starts_at'])  && $today < $cp['starts_at'])   return 'This coupon opens on ' . date('d M Y', strtotime($cp['starts_at'])) . '.';
    if (!empty($cp['expires_at']) && $today > $cp['expires_at'])  return 'This coupon expired on ' . date('d M Y', strtotime($cp['expires_at'])) . '.';
    if ((int)$cp['usage_limit'] > 0 && (int)$cp['used_count'] >= (int)$cp['usage_limit']) return 'This coupon has been fully redeemed.';
    if ($sub <= 0)                                                return 'Add something to your bag first.';
    if ($sub < (float)$cp['min_order'])                           return 'Add ' . money((float)$cp['min_order'] - $sub) . ' more to use ' . $cp['code'] . ' (minimum order ' . money($cp['min_order']) . ').';
    if ($cp['type'] === 'ship' && $sub >= FREE_SHIP_ABOVE)        return 'Your order already qualifies for free shipping.';
    if ($uid && (int)$cp['per_user_limit'] > 0) {
        $st = db()->prepare('SELECT COUNT(*) FROM coupon_uses WHERE coupon_id=? AND user_id=?');
        $st->execute([(int)$cp['id'], $uid]);
        if ((int)$st->fetchColumn() >= (int)$cp['per_user_limit']) return 'You have already used ' . $cp['code'] . ' on a previous order.';
    }
    return '';
}
function coupon_set(?string $code): void {
    if ($code === null) unset($_SESSION['coupon']);
    else $_SESSION['coupon'] = strtoupper(trim($code));
}
/* Coupon currently sitting on the bag (re-read from the DB on every request). */
function active_coupon(): ?array {
    if (empty($_SESSION['coupon'])) return null;
    $cp = coupon_find((string)$_SESSION['coupon']);
    if (!$cp) { unset($_SESSION['coupon']); return null; }
    return $cp;
}
/* Coupons worth showing to the shopper on the cart page. */
function public_coupons(): array {
    return db()->query("SELECT * FROM coupons WHERE status=1 AND is_public=1
                        AND (expires_at IS NULL OR expires_at >= CURDATE())
                        AND (usage_limit=0 OR used_count < usage_limit)
                        ORDER BY min_order ASC, id ASC")->fetchAll();
}
function cart_subtotal(): float {
    $sub = 0;
    foreach (cart_detailed() as $r) $sub += $r['line_total'];
    return (float)$sub;
}
function cart_totals(): array {
    $sub  = cart_subtotal();
    $ship = ($sub > 0 && $sub < FREE_SHIP_ABOVE) ? SHIP_FEE : 0;

    $disc = 0.0; $shipSaved = 0.0; $code = ''; $note = '';
    $cp = active_coupon();
    if ($cp && $sub > 0) {
        $why = coupon_problem($cp, $sub, isset($_SESSION['uid']) ? (int)$_SESSION['uid'] : null);
        if ($why === '') {
            $code = $cp['code'];
            if ($cp['type'] === 'percent') {
                $disc = round($sub * (float)$cp['value'] / 100, 2);
                if ((float)$cp['max_discount'] > 0) $disc = min($disc, (float)$cp['max_discount']);
            } elseif ($cp['type'] === 'flat') {
                $disc = min((float)$cp['value'], $sub);
            } else {                         /* free shipping */
                $shipSaved = $ship;
                $ship      = 0;
            }
        } else {
            $note = $why;                    /* shown on the cart page, coupon stays unapplied */
        }
    }
    $taxable = max(0, $sub - $disc);
    $tax     = round($taxable * TAX_PERCENT / 100, 2);
    return [
        'subtotal'    => $sub,
        'discount'    => round($disc, 2),
        'ship_saved'  => round($shipSaved, 2),
        'saved'       => round($disc + $shipSaved, 2),
        'shipping'    => $ship,
        'tax'         => $tax,
        'total'       => round($taxable + $ship + $tax, 2),
        'coupon'      => $code,
        'coupon_note' => $note,
    ];
}

/* --------------------------- WISHLIST --------------------------- */
function wishlist(): array { return $_SESSION['wish'] ?? []; }
function wish_toggle(int $pid): bool {
    $w = wishlist();
    if (in_array($pid, $w, true)) {
        $w = array_values(array_diff($w, [$pid])); $_SESSION['wish'] = $w; return false;
    }
    $w[] = $pid; $_SESSION['wish'] = $w; return true;
}

/* --------------------------- IMAGE UPLOAD --------------------------- */
function handle_upload(string $field, string $fallbackUrl = ''): string {
    if (!empty($_FILES[$field]['name']) && $_FILES[$field]['error'] === UPLOAD_ERR_OK) {
        if (!is_dir(UPLOAD_DIR)) @mkdir(UPLOAD_DIR, 0777, true);
        $ext = strtolower(pathinfo($_FILES[$field]['name'], PATHINFO_EXTENSION));
        if (in_array($ext, ['jpg', 'jpeg', 'png', 'webp', 'gif'], true)) {
            $name = UPLOAD_DIR . '/' . uniqid('img_', true) . '.' . $ext;
            if (move_uploaded_file($_FILES[$field]['tmp_name'], $name)) return $name;
        }
    }
    return $fallbackUrl;
}

install_schema();

/* ============================ 4. ACTIONS (POST) ======================== */
$action = post('action', (string)get('action', ''));

/* ---- AJAX add to cart ---- */
if ($action === 'ajax_add') {
    header('Content-Type: application/json');
    $pid = (int)post('product_id');
    $qty = max(1, (int)post('qty', '1'));
    $r = cart_add($pid, $qty);
    echo json_encode([
        'ok'    => $r['ok'],
        'warn'  => !empty($r['capped']),
        'msg'   => $r['msg'],
        'count' => cart_count(),
    ]);
    exit;
}
if ($action === 'ajax_wish') {
    header('Content-Type: application/json');
    $added = wish_toggle((int)post('product_id'));
    echo json_encode(['ok' => true, 'added' => $added, 'count' => count(wishlist()),
                      'msg' => $added ? 'Saved to wishlist' : 'Removed from wishlist']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action !== '' && !csrf_ok()
    && !in_array($action, ['ajax_add', 'ajax_wish'], true)) {
    flash('Your session expired. Please try again.', 'error');
    redirect(url(['page' => 'home']));
}

switch ($action) {

    /* ------------------ CART ------------------ */
    case 'cart_add': {
        $r = cart_add((int)post('product_id'), max(1, (int)post('qty', '1')));
        flash($r['msg'], ($r['ok'] && empty($r['capped'])) ? 'success' : 'error');
        redirect(url(['page' => 'cart']));
    }

    case 'cart_update': {
        $notes = [];
        foreach (($_POST['qty'] ?? []) as $pid => $q) {
            $r = cart_update((int)$pid, (int)$q);
            if ($r['msg'] !== '') $notes[] = $r['msg'];
        }
        if ($notes) { foreach ($notes as $n) flash($n, 'error'); }
        else        { flash('Bag updated.'); }
        redirect(url(['page' => 'cart']));
    }

    case 'cart_remove':
        cart_remove((int)post('product_id'));
        flash('Item removed from bag.');
        redirect(url(['page' => 'cart']));

    case 'cart_clear':
        cart_set([]);
        coupon_set(null);
        flash('Bag emptied.');
        redirect(url(['page' => 'cart']));

    /* ------------------ COUPONS ------------------ */
    case 'coupon_apply': {
        $code = strtoupper(post('code'));
        if ($code === '') { flash('Enter a coupon code first.', 'error'); redirect(url(['page' => 'cart'])); }
        $cp = coupon_find($code);
        if (!$cp) { flash('"' . $code . '" is not a valid coupon code.', 'error'); redirect(url(['page' => 'cart'])); }
        $why = coupon_problem($cp, cart_subtotal(), isset($_SESSION['uid']) ? (int)$_SESSION['uid'] : null);
        if ($why !== '') { flash($why, 'error'); redirect(url(['page' => 'cart'])); }
        coupon_set($code);
        $t = cart_totals();
        flash('Coupon ' . $code . ' applied — you saved ' . money($t['saved']) . '.');
        redirect(url(['page' => 'cart']));
    }

    case 'coupon_remove':
        coupon_set(null);
        flash('Coupon removed.');
        redirect(url(['page' => 'cart']));

    /* ------------------ REORDER ------------------ */
    case 'reorder': {
        require_login('orders');
        $u = current_user();
        $o = order_with_items((int)post('order_id'), (int)$u['id']);
        if (!$o) { flash('We could not find that order.', 'error'); redirect(url(['page' => 'orders'])); }

        $added = 0; $notes = [];
        foreach ($o['items'] as $it) {
            $r = cart_add((int)$it['product_id'], (int)$it['qty']);
            if ($r['ok']) $added++;
            if (!$r['ok'] || !empty($r['capped'])) $notes[] = $r['msg'];
        }
        if ($added === 0) {
            foreach ($notes as $n) flash($n, 'error');
            flash('Nothing from order #' . (int)$o['id'] . ' could be added — those items are unavailable right now.', 'error');
            redirect(url(['page' => 'orders']));
        }
        flash($added . ' item' . ($added === 1 ? '' : 's') . ' from order #' . (int)$o['id'] . ' added back to your bag.');
        foreach ($notes as $n) flash($n, 'error');
        redirect(url(['page' => 'cart']));
    }

    /* ------------------ AUTH ------------------ */
    case 'register': {
        $name = post('name'); $email = post('email'); $mobile = post('mobile');
        $pass = post('password'); $cpass = post('cpassword'); $addr = post('address');
        $err = [];
        if ($name === '')                                   $err[] = 'Name is required.';
        if (!filter_var($email, FILTER_VALIDATE_EMAIL))     $err[] = 'Enter a valid email address.';
        if (!preg_match('/^[0-9]{10}$/', $mobile))          $err[] = 'Mobile must be 10 digits.';
        if (strlen($pass) < 6)                              $err[] = 'Password needs at least 6 characters.';
        if ($pass !== $cpass)                               $err[] = 'Passwords do not match.';
        $chk = db()->prepare('SELECT id FROM users WHERE email=?'); $chk->execute([$email]);
        if ($chk->fetch())                                  $err[] = 'That email is already registered.';
        if ($err) { foreach ($err as $x) flash($x, 'error'); redirect(url(['page' => 'register'])); }

        $st = db()->prepare('INSERT INTO users (name,email,mobile,password,address,status,created_at) VALUES (?,?,?,?,?,1,?)');
        $st->execute([$name, $email, $mobile, password_hash($pass, PASSWORD_DEFAULT), $addr, date('Y-m-d H:i:s')]);
        $_SESSION['uid'] = (int)db()->lastInsertId();
        flash('Welcome to ' . SITE_NAME . ', ' . $name . '!');
        redirect(url(['page' => 'home']));
    }

    case 'login': {
        $st = db()->prepare('SELECT * FROM users WHERE email=?');
        $st->execute([post('email')]);
        $u = $st->fetch();
        if (!$u || !password_verify(post('password'), $u['password'])) {
            flash('Incorrect email or password.', 'error');
            redirect(url(['page' => 'login']));
        }
        if ((int)$u['status'] !== 1) {
            flash('This account has been deactivated. Please contact support.', 'error');
            redirect(url(['page' => 'login']));
        }
        $_SESSION['uid'] = (int)$u['id'];
        $next = $_SESSION['after_login'] ?? 'home';
        unset($_SESSION['after_login']);
        flash('Welcome back, ' . $u['name'] . '.');
        redirect(url(['page' => $next]));
    }

    case 'logout':
        unset($_SESSION['uid']);
        flash('You have been logged out.');
        redirect(url(['page' => 'home']));

    case 'profile_update': {
        require_login('profile');
        $u  = current_user();
        $st = db()->prepare('UPDATE users SET name=?,mobile=?,address=?,city=?,state=?,pincode=? WHERE id=?');
        $st->execute([post('name'), post('mobile'), post('address'), post('city'), post('state'), post('pincode'), $u['id']]);
        flash('Profile updated.');
        redirect(url(['page' => 'profile']));
    }

    case 'password_change': {
        require_login('profile');
        $u = current_user();
        if (!password_verify(post('old'), $u['password'])) { flash('Current password is wrong.', 'error'); redirect(url(['page' => 'profile'])); }
        if (strlen(post('new')) < 6)                       { flash('New password is too short.', 'error'); redirect(url(['page' => 'profile'])); }
        $st = db()->prepare('UPDATE users SET password=? WHERE id=?');
        $st->execute([password_hash(post('new'), PASSWORD_DEFAULT), $u['id']]);
        flash('Password changed.');
        redirect(url(['page' => 'profile']));
    }

    /* ------------------ CHECKOUT / ORDER ------------------ */
    case 'checkout': {
        require_login('checkout');
        if (!cart()) { flash('Your bag is empty.', 'error'); redirect(url(['page' => 'shop'])); }
        if ($bad = cart_stock_issues()) {
            foreach ($bad as $b) flash($b, 'error');
            flash('Please fix the quantities in your bag before checking out.', 'error');
            redirect(url(['page' => 'cart']));
        }
        $err = [];
        if (post('customer_name') === '')                            $err[] = 'Name is required.';
        if (!filter_var(post('email'), FILTER_VALIDATE_EMAIL))       $err[] = 'Valid email required.';
        if (!preg_match('/^[0-9]{10}$/', post('mobile')))            $err[] = 'Mobile must be 10 digits.';
        if (post('billing_address') === '')                          $err[] = 'Billing address is required.';
        if (post('city') === '' || post('state') === '')             $err[] = 'City and state are required.';
        if (!preg_match('/^[0-9]{6}$/', post('pincode')))            $err[] = 'Pincode must be 6 digits.';
        if ($err) { foreach ($err as $x) flash($x, 'error'); redirect(url(['page' => 'checkout'])); }

        $_SESSION['checkout'] = [
            'customer_name'    => post('customer_name'),
            'email'            => post('email'),
            'mobile'           => post('mobile'),
            'billing_address'  => post('billing_address'),
            'shipping_address' => post('same_as_billing') === '1' ? post('billing_address') : post('shipping_address'),
            'city'             => post('city'),
            'state'            => post('state'),
            'pincode'          => post('pincode'),
            'note'             => post('note'),
        ];
        redirect(url(['page' => 'payment']));
    }

    case 'place_order': {
        require_login('checkout');
        $items = cart_detailed();
        if (!$items || empty($_SESSION['checkout'])) { flash('Your session expired.', 'error'); redirect(url(['page' => 'cart'])); }

        /* Stock gate #1 — reject before we touch the database at all. */
        if ($bad = cart_stock_issues()) {
            foreach ($bad as $b) flash($b, 'error');
            flash('Your order was NOT placed. Please adjust the quantities in your bag.', 'error');
            redirect(url(['page' => 'cart']));
        }

        $c   = $_SESSION['checkout'];
        $t   = cart_totals();
        $pm  = post('payment_method', 'Cash on Delivery');
        $u   = current_user();
        $cp  = $t['coupon'] !== '' ? coupon_find($t['coupon']) : null;
        $db  = db();
        $db->beginTransaction();
        try {
            $st = $db->prepare('INSERT INTO orders
                (user_id,customer_name,email,mobile,billing_address,shipping_address,city,state,pincode,
                 subtotal,shipping,discount,coupon_code,tax,total,payment_method,payment_status,order_status,note,created_at)
                VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)');
            $st->execute([
                $u['id'], $c['customer_name'], $c['email'], $c['mobile'], $c['billing_address'],
                $c['shipping_address'], $c['city'], $c['state'], $c['pincode'],
                $t['subtotal'], $t['shipping'], $t['discount'], $t['coupon'], $t['tax'], $t['total'],
                $pm, ($pm === 'Cash on Delivery' ? 'Pending' : 'Paid'), 'Pending',
                $c['note'], date('Y-m-d H:i:s')
            ]);
            $oid = (int)$db->lastInsertId();

            $ins = $db->prepare('INSERT INTO order_items (order_id,product_id,product_name,product_image,price,qty,line_total)
                                 VALUES (?,?,?,?,?,?,?)');
            /* Stock gate #2 — the decrement itself refuses to go below zero, so two
               shoppers buying the last pieces at the same moment cannot both succeed. */
            $dec  = $db->prepare('UPDATE products SET stock = stock - ? WHERE id=? AND stock >= ?');
            $left = $db->prepare('SELECT stock FROM products WHERE id=?');
            foreach ($items as $it) {
                $dec->execute([$it['qty'], $it['id'], $it['qty']]);
                if ($dec->rowCount() === 0) {
                    $left->execute([$it['id']]);
                    $have = (int)$left->fetchColumn();
                    throw new RuntimeException('"' . $it['name'] . '" just ran short — only ' . $have
                        . ' left, but your bag asks for ' . (int)$it['qty'] . '. Your order was NOT placed.');
                }
                $ins->execute([$oid, $it['id'], $it['name'], $it['image'], $it['unit_price'], $it['qty'], $it['line_total']]);
            }

            if ($cp) {
                $db->prepare('UPDATE coupons SET used_count = used_count + 1 WHERE id=?')->execute([(int)$cp['id']]);
                $db->prepare('INSERT INTO coupon_uses (coupon_id,user_id,order_id,amount,created_at) VALUES (?,?,?,?,?)')
                   ->execute([(int)$cp['id'], (int)$u['id'], $oid, $t['saved'], date('Y-m-d H:i:s')]);
            }
            $db->commit();
        } catch (RuntimeException $ex) {
            $db->rollBack();
            flash($ex->getMessage(), 'error');
            redirect(url(['page' => 'cart']));
        } catch (Throwable $ex) {
            $db->rollBack();
            flash('Could not place the order. Please try again.', 'error');
            redirect(url(['page' => 'payment']));
        }
        cart_set([]);
        coupon_set(null);
        unset($_SESSION['checkout']);
        $_SESSION['last_order'] = $oid;
        redirect(url(['page' => 'success', 'id' => $oid]));
    }

    case 'contact_send': {
        if (post('name') === '' || !filter_var(post('email'), FILTER_VALIDATE_EMAIL) || post('message') === '') {
            flash('Please fill in your name, a valid email and a message.', 'error');
            redirect(url(['page' => 'contact']));
        }
        $st = db()->prepare('INSERT INTO contacts (name,email,subject,message,created_at) VALUES (?,?,?,?,?)');
        $st->execute([post('name'), post('email'), post('subject'), post('message'), date('Y-m-d H:i:s')]);
        flash('Thanks! Your message has reached our team.');
        redirect(url(['page' => 'contact']));
    }

    /* ======================= ADMIN ACTIONS ======================= */
    case 'admin_login': {
        $st = db()->prepare('SELECT * FROM admins WHERE username=?');
        $st->execute([post('username')]);
        $a = $st->fetch();
        if (!$a || !password_verify(post('password'), $a['password'])) {
            flash('Invalid administrator credentials.', 'error');
            redirect(url(['page' => 'admin_login']));
        }
        $_SESSION['aid'] = (int)$a['id'];
        redirect(url(['page' => 'admin_dashboard']));
    }
    case 'admin_logout':
        unset($_SESSION['aid']);
        redirect(url(['page' => 'admin_login']));

    case 'cat_save': {
        require_admin();
        $id = (int)post('id');
        $img = handle_upload('image_file', post('image'));
        if ($id) {
            $st = db()->prepare('UPDATE categories SET name=?,slug=?,image=?,description=?,status=? WHERE id=?');
            $st->execute([post('name'), slugify(post('name')), $img, post('description'), (int)post('status'), $id]);
            flash('Category updated.');
        } else {
            $st = db()->prepare('INSERT INTO categories (name,slug,image,description,status,created_at) VALUES (?,?,?,?,?,?)');
            $st->execute([post('name'), slugify(post('name')), $img, post('description'), (int)post('status'), date('Y-m-d H:i:s')]);
            flash('Category added.');
        }
        redirect(url(['page' => 'admin_categories']));
    }
    case 'cat_delete': {
        require_admin();
        $id = (int)post('id');
        $n  = db()->prepare('SELECT COUNT(*) FROM products WHERE category_id=?'); $n->execute([$id]);
        if ((int)$n->fetchColumn() > 0) { flash('Remove or move its products first.', 'error'); redirect(url(['page' => 'admin_categories'])); }
        db()->prepare('DELETE FROM categories WHERE id=?')->execute([$id]);
        flash('Category deleted.');
        redirect(url(['page' => 'admin_categories']));
    }

    case 'prod_save': {
        require_admin();
        $id  = (int)post('id');
        $img = handle_upload('image_file', post('image'));
        $data = [
            (int)post('category_id'), post('name'), $img,
            (float)post('price'), (float)post('discount'), post('short_desc'),
            post('description'), post('specs'), (int)post('stock'),
            (float)post('rating', '4.5'), (int)post('is_featured'), (int)post('status')
        ];
        if ($id) {
            $st = db()->prepare('UPDATE products SET category_id=?,name=?,image=?,price=?,discount=?,short_desc=?,
                                 description=?,specs=?,stock=?,rating=?,is_featured=?,status=? WHERE id=?');
            $data[] = $id; $st->execute($data);
            flash('Product updated.');
        } else {
            $st = db()->prepare('INSERT INTO products (category_id,name,image,price,discount,short_desc,description,specs,
                                 stock,rating,is_featured,status,created_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)');
            $data[] = date('Y-m-d H:i:s'); $st->execute($data);
            flash('Product added.');
        }
        redirect(url(['page' => 'admin_products']));
    }
    case 'prod_delete': {
        require_admin();
        db()->prepare('DELETE FROM products WHERE id=?')->execute([(int)post('id')]);
        flash('Product deleted.');
        redirect(url(['page' => 'admin_products']));
    }

    case 'user_save': {
        require_admin();
        $st = db()->prepare('UPDATE users SET name=?,email=?,mobile=?,address=?,city=?,state=?,pincode=?,status=? WHERE id=?');
        $st->execute([post('name'), post('email'), post('mobile'), post('address'), post('city'),
                      post('state'), post('pincode'), (int)post('status'), (int)post('id')]);
        flash('Customer updated.');
        redirect(url(['page' => 'admin_users']));
    }
    case 'user_delete': {
        require_admin();
        db()->prepare('DELETE FROM users WHERE id=?')->execute([(int)post('id')]);
        flash('Customer deleted.');
        redirect(url(['page' => 'admin_users']));
    }

    case 'order_status': {
        require_admin();
        $st = db()->prepare('UPDATE orders SET order_status=?, payment_status=? WHERE id=?');
        $st->execute([post('order_status'), post('payment_status'), (int)post('id')]);
        flash('Order #' . (int)post('id') . ' updated.');
        redirect(url(['page' => 'admin_order_view', 'id' => (int)post('id')]));
    }
    case 'coupon_save': {
        require_admin();
        $id   = (int)post('id');
        $code = strtoupper(preg_replace('/[^A-Za-z0-9_-]/', '', post('code')));
        $type = in_array(post('type'), ['percent', 'flat', 'ship'], true) ? post('type') : 'percent';
        if ($code === '') { flash('A coupon needs a code.', 'error'); redirect(url(['page' => 'admin_coupons'])); }

        $dup = db()->prepare('SELECT id FROM coupons WHERE code=? AND id<>?');
        $dup->execute([$code, $id]);
        if ($dup->fetch()) { flash('The code ' . $code . ' is already in use.', 'error'); redirect(url(['page' => 'admin_coupons'])); }

        $val = (float)post('value');
        if ($type === 'percent' && ($val <= 0 || $val > 90)) { flash('A percentage coupon must be between 1 and 90.', 'error'); redirect(url(['page' => 'admin_coupons'])); }
        if ($type === 'flat' && $val <= 0)                   { flash('A flat coupon needs an amount greater than zero.', 'error'); redirect(url(['page' => 'admin_coupons'])); }
        if ($type === 'ship') $val = 0;

        $exp = post('expires_at') !== '' ? post('expires_at') : null;
        $fields = [
            $code, post('title'), $type, $val, (float)post('min_order'), (float)post('max_discount'),
            (int)post('usage_limit'), (int)post('per_user_limit'), $exp,
            (int)(post('is_public') === '1'), (int)(post('status') === '1'),
        ];
        if ($id) {
            $st = db()->prepare('UPDATE coupons SET code=?,title=?,type=?,value=?,min_order=?,max_discount=?,
                                 usage_limit=?,per_user_limit=?,expires_at=?,is_public=?,status=? WHERE id=?');
            $fields[] = $id;
            $st->execute($fields);
            flash('Coupon ' . $code . ' updated.');
        } else {
            $st = db()->prepare('INSERT INTO coupons (code,title,type,value,min_order,max_discount,
                                 usage_limit,per_user_limit,expires_at,is_public,status,used_count,created_at)
                                 VALUES (?,?,?,?,?,?,?,?,?,?,?,0,?)');
            $fields[] = date('Y-m-d H:i:s');
            $st->execute($fields);
            flash('Coupon ' . $code . ' created.');
        }
        redirect(url(['page' => 'admin_coupons']));
    }

    case 'coupon_delete': {
        require_admin();
        $st = db()->prepare('DELETE FROM coupons WHERE id=?');
        $st->execute([(int)post('id')]);
        flash('Coupon deleted.');
        redirect(url(['page' => 'admin_coupons']));
    }

    case 'contact_read': {
        require_admin();
        db()->prepare('UPDATE contacts SET is_read=1 WHERE id=?')->execute([(int)post('id')]);
        redirect(url(['page' => 'admin_messages']));
    }
}

/* ============================ 5. ROUTING ============================== */
$page     = (string)get('page', 'home');
$is_admin = strpos($page, 'admin_') === 0;
$flashes  = take_flashes();

/* ============================ 6. QUERIES ============================== */
function all_categories(bool $onlyActive = true): array {
    $sql = 'SELECT c.*, (SELECT COUNT(*) FROM products p WHERE p.category_id=c.id AND p.status=1) AS product_count
            FROM categories c ' . ($onlyActive ? 'WHERE c.status=1 ' : '') . 'ORDER BY c.name';
    return db()->query($sql)->fetchAll();
}
function find_product(int $id): ?array {
    $st = db()->prepare('SELECT p.*, c.name AS category_name, c.slug AS category_slug
                         FROM products p LEFT JOIN categories c ON c.id=p.category_id WHERE p.id=?');
    $st->execute([$id]);
    return $st->fetch() ?: null;
}
function products_query(array $o = []): array {
    $w = ['p.status=1']; $b = [];
    if (!empty($o['category'])) { $w[] = 'p.category_id=?';        $b[] = (int)$o['category']; }
    if (!empty($o['search']))   { $w[] = '(p.name LIKE ? OR p.short_desc LIKE ?)'; $b[] = '%' . $o['search'] . '%'; $b[] = '%' . $o['search'] . '%'; }
    if (!empty($o['featured'])) { $w[] = 'p.is_featured=1'; }
    $order = 'p.created_at DESC';
    switch ($o['sort'] ?? '') {
        case 'price_low':  $order = 'IF(p.discount>0,p.discount,p.price) ASC';  break;
        case 'price_high': $order = 'IF(p.discount>0,p.discount,p.price) DESC'; break;
        case 'rating':     $order = 'p.rating DESC'; break;
        case 'name':       $order = 'p.name ASC';    break;
    }
    $sql = 'SELECT p.*, c.name AS category_name FROM products p
            LEFT JOIN categories c ON c.id=p.category_id
            WHERE ' . implode(' AND ', $w) . ' ORDER BY ' . $order;
    if (!empty($o['limit'])) $sql .= ' LIMIT ' . (int)$o['limit'];
    $st = db()->prepare($sql); $st->execute($b);
    return $st->fetchAll();
}
function user_orders(int $uid): array {
    $st = db()->prepare('SELECT o.*, (SELECT COALESCE(SUM(qty),0) FROM order_items i WHERE i.order_id=o.id) AS item_count
                         FROM orders o WHERE o.user_id=? ORDER BY o.id DESC');
    $st->execute([$uid]);
    return $st->fetchAll();
}
function order_with_items(int $id, ?int $uid = null): ?array {
    $sql = 'SELECT * FROM orders WHERE id=?'; $b = [$id];
    if ($uid !== null) { $sql .= ' AND user_id=?'; $b[] = $uid; }
    $st = db()->prepare($sql); $st->execute($b);
    $o  = $st->fetch();
    if (!$o) return null;
    $it = db()->prepare('SELECT * FROM order_items WHERE order_id=?'); $it->execute([$id]);
    $o['items'] = $it->fetchAll();
    return $o;
}
function status_class(string $s): string {
    return 'stx ' . strtolower(str_replace(' ', '', $s));
}

/* ---- QUICK VIEW FRAGMENT (returned to fetch(), no layout) ---- */
if ($page === 'quickview') {
    $p = find_product((int)get('id', 0));
    if (!$p) { echo '<p style="padding:30px;text-align:center">Product not found.</p>'; exit; }
    $disc = discount_percent($p);
    ?>
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:26px;align-items:start">
      <div style="border-radius:12px;overflow:hidden;background:#F4EBE0">
        <img src="<?= img_src($p['image'], $p['name'] ?? '', $p['category_slug'] ?? '') ?>" alt="<?= e($p['name']) ?>" style="width:100%;aspect-ratio:1/1;object-fit:cover" onerror="this.onerror=null;this.src='<?= e(art_fallback($p['name'] ?? '', $p['category_slug'] ?? '')) ?>';">
      </div>
      <div>
        <div class="eyebrow"><?= e($p['category_name']) ?></div>
        <h2 style="font-size:30px;margin:8px 0 10px"><?= e($p['name']) ?></h2>
        <div class="rate-row"><?= star_html((float)$p['rating']) ?><span><?= number_format((float)$p['rating'], 1) ?></span></div>
        <div class="pd-price" style="margin:14px 0">
          <span class="now" style="font-size:30px"><?= money(final_price($p)) ?></span>
          <?php if ($disc): ?><span class="was"><?= money($p['price']) ?></span>
            <span class="pill"><?= $disc ?>% off</span><?php endif; ?>
        </div>
        <p style="font-size:14px;color:#544942"><?= e($p['short_desc']) ?></p>
        <p style="margin-top:10px">
          <?php if ((int)$p['stock'] > 0): ?><span class="stx delivered">In stock</span>
          <?php else: ?><span class="stx cancelled">Out of stock</span><?php endif; ?>
        </p>
        <div style="display:flex;gap:10px;margin-top:20px;flex-wrap:wrap">
          <button class="btn" <?= (int)$p['stock'] < 1 ? 'disabled' : '' ?>
                  onclick="addToCart(<?= (int)$p['id'] ?>,1,this)"><span>Add to bag</span></button>
          <a class="btn ghost" href="<?= url(['page' => 'product', 'id' => $p['id']]) ?>"><span>Full details</span></a>
        </div>
      </div>
    </div>
    <?php
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e(SITE_NAME) ?> — <?= $is_admin ? 'Admin Panel' : 'Clean Beauty & Skincare' ?></title>
<meta name="description" content="<?= e(SITE_TAG) ?> — skincare, makeup, haircare and fragrance.">
<link rel="icon" href="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 32 32'%3E%3Crect width='32' height='32' rx='8' fill='%23B9603F'/%3E%3Ctext x='16' y='22' font-size='17' font-family='Georgia' text-anchor='middle' fill='%23FDF8F2'%3EL%3C/text%3E%3C/svg%3E">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:wght@500;600;700&family=Jost:wght@300;400;500;600&display=swap" rel="stylesheet">
<script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.0/chart.umd.min.js"></script>
<style>
/* ================= DESIGN TOKENS ================= */
:root{
  --clay:#B9603F;         /* primary  */
  --clay-dk:#94472B;
  --clay-lt:#F3E2DA;
  --ink:#241C19;          /* headings */
  --ink-2:#3B302B;
  --body:#544942;
  --mute:#8B7D74;
  --cream:#FDF8F2;        /* page bg  */
  --sand:#F4EBE0;
  --line:#E7D9CB;
  --white:#FFFFFF;
  --moss:#3E5C47;         /* success  */
  --moss-lt:#E4EDE6;
  --gold:#C08B2E;
  --gold-lt:#FAF0D9;
  --danger:#A63A2E;
  --danger-lt:#FBE6E2;
  --r-s:6px; --r-m:12px; --r-l:20px;
  --sh-1:0 2px 8px rgba(36,28,25,.05);
  --sh-2:0 14px 40px rgba(36,28,25,.12);
  --sh-3:0 26px 70px rgba(36,28,25,.18);
  --ease:cubic-bezier(.22,.9,.28,1);
}
*{box-sizing:border-box;margin:0;padding:0}
html{scroll-behavior:smooth}
body{
  font-family:'Jost',system-ui,sans-serif;font-weight:400;font-size:15.5px;
  color:var(--body);background:var(--cream);line-height:1.65;-webkit-font-smoothing:antialiased;
}
h1,h2,h3,h4,.disp{font-family:'Cormorant Garamond',Georgia,serif;color:var(--ink);font-weight:600;line-height:1.15;letter-spacing:.005em}
h1{font-size:clamp(38px,6vw,68px)}
h2{font-size:clamp(28px,4vw,42px)}
h3{font-size:22px}
a{color:inherit;text-decoration:none}
img{max-width:100%;display:block}
.wrap{width:100%;max-width:1240px;margin:0 auto;padding:0 22px}
.center{text-align:center}
.eyebrow{font-size:11.5px;letter-spacing:.22em;text-transform:uppercase;color:var(--clay);font-weight:500}
.lede{color:var(--mute);max-width:560px}
.sec{padding:78px 0}
.sec-head{display:flex;justify-content:space-between;align-items:flex-end;gap:20px;flex-wrap:wrap;margin-bottom:34px}

/* ================= BUTTONS ================= */
.btn{
  display:inline-flex;align-items:center;justify-content:center;gap:9px;
  font-family:'Jost',sans-serif;font-size:13.5px;font-weight:500;letter-spacing:.06em;text-transform:uppercase;
  padding:14px 30px;border-radius:999px;border:1px solid var(--clay);background:var(--clay);color:#fff;
  cursor:pointer;position:relative;overflow:hidden;transition:.35s var(--ease);
}
.btn::after{content:"";position:absolute;inset:0;background:var(--clay-dk);transform:translateY(101%);transition:.4s var(--ease);z-index:0}
.btn:hover::after{transform:translateY(0)}
.btn>*{position:relative;z-index:1}
.btn:hover{box-shadow:0 12px 26px rgba(185,96,63,.32);transform:translateY(-2px)}
.btn:active{transform:translateY(0) scale(.98)}
.btn.ghost{background:transparent;color:var(--ink);border-color:var(--line)}
.btn.ghost::after{background:var(--sand)}
.btn.ghost:hover{box-shadow:var(--sh-1);border-color:var(--clay)}
.btn.dark{background:var(--ink);border-color:var(--ink)}
.btn.dark::after{background:#3a2e29}
.btn.sm{padding:9px 18px;font-size:12px}
.btn.wide{width:100%}
.btn[disabled]{opacity:.45;pointer-events:none}
.link-u{position:relative;font-size:13px;letter-spacing:.1em;text-transform:uppercase;color:var(--ink);padding-bottom:3px}
.link-u::after{content:"";position:absolute;left:0;bottom:0;height:1px;width:100%;background:var(--clay);transform:scaleX(0);transform-origin:right;transition:.4s var(--ease)}
.link-u:hover::after{transform:scaleX(1);transform-origin:left}

/* ================= HEADER ================= */
.announce{background:var(--ink);color:#EEE3D9;font-size:12.5px;letter-spacing:.08em;text-align:center;padding:9px 16px;overflow:hidden}
.announce span{display:inline-block;animation:slideNote 12s linear infinite}
@keyframes slideNote{0%,28%{transform:translateY(0)}33%,61%{transform:translateY(-100%)}66%,94%{transform:translateY(-200%)}100%{transform:translateY(-200%)}}
.announce b{color:var(--clay-lt)}
header.site{position:sticky;top:0;z-index:60;background:rgba(253,248,242,.88);backdrop-filter:blur(14px);border-bottom:1px solid var(--line);transition:.3s}
header.site.shrunk{box-shadow:var(--sh-1)}
.nav{display:flex;align-items:center;gap:26px;padding:16px 0}
.logo{font-family:'Cormorant Garamond',serif;font-size:30px;font-weight:700;color:var(--ink);letter-spacing:.06em;line-height:1}
.logo small{display:block;font-family:'Jost';font-size:8.5px;letter-spacing:.34em;color:var(--mute);text-transform:uppercase;font-weight:400;margin-top:3px}
.menu{display:flex;gap:26px;margin-left:16px}
.menu a{font-size:13.5px;letter-spacing:.09em;text-transform:uppercase;color:var(--ink-2);position:relative;padding:4px 0}
.menu a::after{content:"";position:absolute;left:0;bottom:-2px;height:1.5px;width:100%;background:var(--clay);transform:scaleX(0);transform-origin:right;transition:.35s var(--ease)}
.menu a:hover::after,.menu a.on::after{transform:scaleX(1);transform-origin:left}
.menu a.on{color:var(--clay)}
.nav-tools{margin-left:auto;display:flex;align-items:center;gap:8px}
.ico{width:42px;height:42px;border-radius:50%;display:grid;place-items:center;border:1px solid transparent;color:var(--ink);position:relative;transition:.25s var(--ease);background:none;cursor:pointer}
.ico:hover{background:var(--sand);border-color:var(--line);transform:translateY(-2px)}
.ico svg{width:19px;height:19px;stroke:currentColor;fill:none;stroke-width:1.6;stroke-linecap:round;stroke-linejoin:round}
.badge{position:absolute;top:4px;right:3px;min-width:18px;height:18px;padding:0 5px;border-radius:9px;background:var(--clay);color:#fff;font-size:10.5px;font-weight:600;display:grid;place-items:center;line-height:1}
.badge.bump{animation:bump .45s var(--ease)}
@keyframes bump{0%{transform:scale(1)}45%{transform:scale(1.45)}100%{transform:scale(1)}}
.burger{display:none}
.searchbar{display:flex;align-items:center;gap:8px;background:var(--white);border:1px solid var(--line);border-radius:999px;padding:8px 8px 8px 16px}
.searchbar input{border:none;outline:none;background:none;font-family:'Jost';font-size:14px;width:150px;color:var(--ink)}
.searchbar button{border:none;background:var(--clay);color:#fff;width:32px;height:32px;border-radius:50%;cursor:pointer;display:grid;place-items:center;transition:.25s}
.searchbar button:hover{background:var(--clay-dk);transform:rotate(-8deg)}

/* ================= HERO SLIDER ================= */
.hero{position:relative;height:min(84vh,680px);overflow:hidden;background:var(--sand)}
.slide{position:absolute;inset:0;opacity:0;transition:opacity 1.1s var(--ease);display:grid;place-items:center}
.slide.on{opacity:1}
.slide .bgimg{position:absolute;inset:0;background-size:cover;background-position:center;transform:scale(1.1);transition:transform 7s linear}
.slide.on .bgimg{transform:scale(1)}
.slide::before{content:"";position:absolute;inset:0;background:linear-gradient(100deg,rgba(28,20,16,.78) 0%,rgba(28,20,16,.45) 48%,rgba(28,20,16,.12) 100%);z-index:1}
.slide-in{position:relative;z-index:2;max-width:1240px;width:100%;padding:0 22px;color:#fff}
.slide-in .eyebrow{color:#EBC6B4}
.slide h1{color:#fff;max-width:620px;margin:14px 0 16px}
.slide p{color:#E8DDD4;max-width:440px;margin-bottom:30px}
.slide .btns{display:flex;gap:12px;flex-wrap:wrap}
.slide.on .slide-in>*{animation:heroUp .9s var(--ease) both}
.slide.on .slide-in>*:nth-child(2){animation-delay:.12s}
.slide.on .slide-in>*:nth-child(3){animation-delay:.22s}
.slide.on .slide-in>*:nth-child(4){animation-delay:.32s}
@keyframes heroUp{from{opacity:0;transform:translateY(26px)}to{opacity:1;transform:translateY(0)}}
.hero-dots{position:absolute;left:0;right:0;bottom:28px;z-index:5;display:flex;justify-content:center;gap:10px}
.hero-dots button{width:34px;height:3px;border:none;background:rgba(255,255,255,.4);cursor:pointer;border-radius:2px;transition:.3s;padding:0}
.hero-dots button.on{background:var(--clay);width:52px}
.hero-arrow{position:absolute;top:50%;z-index:5;transform:translateY(-50%);width:46px;height:46px;border-radius:50%;border:1px solid rgba(255,255,255,.35);background:rgba(255,255,255,.08);color:#fff;display:grid;place-items:center;cursor:pointer;transition:.3s;backdrop-filter:blur(4px)}
.hero-arrow:hover{background:var(--clay);border-color:var(--clay)}
.hero-arrow.prev{left:22px}.hero-arrow.next{right:22px}

/* ================= MARQUEE / TRUST ================= */
.trust{background:var(--ink);color:#E3D6CB;padding:16px 0;overflow:hidden;white-space:nowrap}
.trust-track{display:inline-flex;gap:56px;animation:marq 26s linear infinite;font-size:12.5px;letter-spacing:.18em;text-transform:uppercase}
.trust-track span{display:inline-flex;align-items:center;gap:10px}
.trust-track i{color:var(--clay);font-style:normal}
@keyframes marq{from{transform:translateX(0)}to{transform:translateX(-50%)}}

/* ================= CATEGORY CARDS ================= */
.cat-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(240px,1fr));gap:20px}
.cat-card{position:relative;border-radius:var(--r-l);overflow:hidden;aspect-ratio:3/4;box-shadow:var(--sh-1);transition:.45s var(--ease)}
.cat-card img{width:100%;height:100%;object-fit:cover;transition:1s var(--ease)}
.cat-card::after{content:"";position:absolute;inset:0;background:linear-gradient(to top,rgba(28,20,16,.82),rgba(28,20,16,.06) 62%)}
.cat-card:hover{transform:translateY(-8px);box-shadow:var(--sh-2)}
.cat-card:hover img{transform:scale(1.09)}
.cat-body{position:absolute;left:0;right:0;bottom:0;padding:24px;z-index:2;color:#fff}
.cat-body h3{color:#fff;font-size:25px;margin-bottom:3px}
.cat-body p{font-size:12.5px;color:#DCCBBF;letter-spacing:.06em}
.cat-body .go{display:inline-flex;align-items:center;gap:7px;margin-top:12px;font-size:12px;letter-spacing:.14em;text-transform:uppercase;color:#fff;opacity:0;transform:translateY(8px);transition:.4s var(--ease)}
.cat-card:hover .go{opacity:1;transform:translateY(0)}

/* ================= PRODUCT CARDS ================= */
.p-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(250px,1fr));gap:26px}
.p-card{background:var(--white);border:1px solid var(--line);border-radius:var(--r-m);overflow:hidden;display:flex;flex-direction:column;transition:.4s var(--ease);position:relative}
.p-card:hover{transform:translateY(-7px);box-shadow:var(--sh-2);border-color:#D9C4B2}
.p-media{position:relative;aspect-ratio:1/1;overflow:hidden;background:var(--sand)}
.p-media img{width:100%;height:100%;object-fit:cover;transition:1.1s var(--ease)}
.p-card:hover .p-media img{transform:scale(1.08)}
.p-tags{position:absolute;top:12px;left:12px;display:flex;flex-direction:column;gap:6px;z-index:2}
.tag{font-size:10.5px;letter-spacing:.12em;text-transform:uppercase;padding:5px 11px;border-radius:999px;font-weight:500;background:var(--clay);color:#fff}
.tag.new{background:var(--moss)}
.tag.out{background:var(--ink)}
.p-actions{position:absolute;top:12px;right:12px;display:flex;flex-direction:column;gap:8px;z-index:3}
.p-actions button{width:38px;height:38px;border-radius:50%;border:none;background:rgba(255,255,255,.94);color:var(--ink);display:grid;place-items:center;cursor:pointer;opacity:0;transform:translateX(12px);transition:.35s var(--ease);box-shadow:var(--sh-1)}
.p-card:hover .p-actions button{opacity:1;transform:translateX(0)}
.p-card:hover .p-actions button:nth-child(2){transition-delay:.06s}
.p-actions button:hover{background:var(--clay);color:#fff}
.p-actions button.active{background:var(--clay);color:#fff;opacity:1;transform:none}
.p-actions svg{width:17px;height:17px;stroke:currentColor;fill:none;stroke-width:1.6}
.p-cart{position:absolute;left:12px;right:12px;bottom:12px;z-index:3;opacity:0;transform:translateY(14px);transition:.4s var(--ease)}
.p-card:hover .p-cart{opacity:1;transform:translateY(0)}
.p-body{padding:18px 18px 20px;display:flex;flex-direction:column;gap:7px;flex:1}
.p-cat{font-size:10.5px;letter-spacing:.16em;text-transform:uppercase;color:var(--mute)}
.p-title{font-size:19px;line-height:1.25}
.p-title a:hover{color:var(--clay)}
.p-desc{font-size:13px;color:var(--mute);line-height:1.5}
.stars{display:inline-flex;gap:1px;font-size:13px}
.st{color:#DCCFC3}.st.on{color:var(--gold)}
.rate-row{display:flex;align-items:center;gap:7px;font-size:12px;color:var(--mute)}
.p-price{margin-top:auto;display:flex;align-items:baseline;gap:9px;padding-top:6px}
.price-now{font-family:'Cormorant Garamond',serif;font-size:24px;color:var(--ink);font-weight:600}
.price-was{font-size:13.5px;color:var(--mute);text-decoration:line-through}
.save{font-size:11px;color:var(--moss);font-weight:500}

/* ================= OFFER BAND ================= */
.offer{background:linear-gradient(120deg,var(--ink) 0%,#3C2C24 60%,var(--clay-dk) 140%);color:#F3E6DC;border-radius:var(--r-l);padding:52px 44px;display:flex;align-items:center;gap:36px;flex-wrap:wrap;position:relative;overflow:hidden}
.offer::before{content:"";position:absolute;right:-70px;top:-70px;width:280px;height:280px;border-radius:50%;background:radial-gradient(circle,rgba(185,96,63,.55),transparent 68%)}
.offer h2{color:#fff;max-width:440px}
.offer p{color:#D6C4B7;max-width:420px;margin:10px 0 22px}
.offer .right{margin-left:auto;position:relative;z-index:2;text-align:center}
.countdown{display:flex;gap:12px}
.cd-box{background:rgba(255,255,255,.08);border:1px solid rgba(255,255,255,.16);border-radius:var(--r-m);padding:14px 16px;min-width:74px;backdrop-filter:blur(6px)}
.cd-box b{display:block;font-family:'Cormorant Garamond',serif;font-size:32px;color:#fff;line-height:1;font-variant-numeric:tabular-nums}
.cd-box span{font-size:10px;letter-spacing:.16em;text-transform:uppercase;color:#C6B2A4}

/* ================= TESTIMONIALS ================= */
.quote-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(280px,1fr));gap:22px}
.quote{background:var(--white);border:1px solid var(--line);border-radius:var(--r-m);padding:28px;position:relative;transition:.4s var(--ease)}
.quote:hover{transform:translateY(-5px);box-shadow:var(--sh-1)}
.quote .qm{font-family:'Cormorant Garamond',serif;font-size:62px;color:var(--clay-lt);line-height:.7;margin-bottom:6px}
.quote p{font-size:14.5px;color:var(--ink-2);font-style:italic}
.quote .who{margin-top:16px;font-size:12.5px;letter-spacing:.1em;text-transform:uppercase;color:var(--mute)}

/* ================= NEWSLETTER + FOOTER ================= */
.news{background:var(--sand);border-radius:var(--r-l);padding:52px 44px;text-align:center}
.news form{display:flex;gap:10px;max-width:470px;margin:22px auto 0;flex-wrap:wrap}
.news input{flex:1;min-width:210px;padding:14px 20px;border:1px solid var(--line);border-radius:999px;background:#fff;font-family:'Jost';font-size:14px;outline:none}
.news input:focus{border-color:var(--clay)}
footer.site{background:var(--ink);color:#C7B8AC;margin-top:70px;padding:64px 0 0}
.f-grid{display:grid;grid-template-columns:1.6fr 1fr 1fr 1fr;gap:34px}
footer.site h4{color:#fff;font-size:15px;font-family:'Jost';font-weight:500;letter-spacing:.14em;text-transform:uppercase;margin-bottom:16px}
footer.site .logo{color:#fff}
footer.site p{font-size:14px;max-width:290px}
footer.site ul{list-style:none;display:flex;flex-direction:column;gap:10px}
footer.site a{font-size:14px;transition:.25s}
footer.site a:hover{color:var(--clay-lt);padding-left:5px}
.socials{display:flex;gap:10px;margin-top:18px}
.socials a{width:38px;height:38px;border-radius:50%;border:1px solid rgba(255,255,255,.16);display:grid;place-items:center;transition:.3s}
.socials a:hover{background:var(--clay);border-color:var(--clay);color:#fff;padding:0;transform:translateY(-3px)}
.socials svg{width:16px;height:16px;stroke:currentColor;fill:none;stroke-width:1.6}
.f-bot{border-top:1px solid rgba(255,255,255,.1);margin-top:44px;padding:20px 0;display:flex;justify-content:space-between;gap:14px;flex-wrap:wrap;font-size:13px}

/* ================= PAGE HEADER / BREADCRUMB ================= */
.phead{background:var(--sand);padding:52px 0;border-bottom:1px solid var(--line)}
.crumb{font-size:12.5px;color:var(--mute);letter-spacing:.06em;margin-bottom:8px}
.crumb a:hover{color:var(--clay)}

/* ================= FORMS / PANELS ================= */
.panel{background:var(--white);border:1px solid var(--line);border-radius:var(--r-m);padding:28px;box-shadow:var(--sh-1)}
.fgrid{display:grid;grid-template-columns:1fr 1fr;gap:18px}
.fld{display:flex;flex-direction:column;gap:7px}
.fld.full{grid-column:1/-1}
label{font-size:12px;letter-spacing:.1em;text-transform:uppercase;color:var(--ink-2);font-weight:500}
input[type=text],input[type=email],input[type=password],input[type=number],input[type=url],input[type=tel],input[type=file],select,textarea{
  font-family:'Jost';font-size:14.5px;padding:12px 14px;border:1px solid var(--line);border-radius:var(--r-s);
  background:var(--cream);color:var(--ink);outline:none;transition:.25s;width:100%
}
input:focus,select:focus,textarea:focus{border-color:var(--clay);background:#fff;box-shadow:0 0 0 3px rgba(185,96,63,.12)}
textarea{min-height:110px;resize:vertical}
.hint{font-size:12px;color:var(--mute)}
.check{display:flex;align-items:center;gap:9px;font-size:14px;text-transform:none;letter-spacing:0}
.check input{width:16px;height:16px;accent-color:var(--clay)}

/* ================= TABLES ================= */
.tbl{width:100%;border-collapse:collapse;background:#fff;border:1px solid var(--line);border-radius:var(--r-m);overflow:hidden}
.tbl th{text-align:left;font-size:11px;letter-spacing:.14em;text-transform:uppercase;color:var(--mute);font-weight:500;padding:14px 16px;background:var(--sand);border-bottom:1px solid var(--line)}
.tbl td{padding:15px 16px;border-bottom:1px solid var(--line);font-size:14px;vertical-align:middle}
.tbl tr:last-child td{border-bottom:none}
.tbl tbody tr{transition:.2s}
.tbl tbody tr:hover{background:#FBF6F0}
.thumb{width:54px;height:54px;border-radius:var(--r-s);object-fit:cover;background:var(--sand)}

/* ================= STATUS CHIPS ================= */
.stx{font-size:11px;letter-spacing:.1em;text-transform:uppercase;padding:5px 12px;border-radius:999px;font-weight:500;display:inline-block;white-space:nowrap}
.stx.pending{background:var(--gold-lt);color:#8A6212}
.stx.confirmed{background:var(--clay-lt);color:var(--clay-dk)}
.stx.processing{background:#E7EDF5;color:#3B5A80}
.stx.shipped{background:#E9E6F4;color:#5B4B92}
.stx.delivered{background:var(--moss-lt);color:var(--moss)}
.stx.cancelled{background:var(--danger-lt);color:var(--danger)}
.stx.paid{background:var(--moss-lt);color:var(--moss)}
.stx.failed{background:var(--danger-lt);color:var(--danger)}
.stx.refunded{background:#EFEAE4;color:var(--mute)}
.stx.active{background:var(--moss-lt);color:var(--moss)}
.stx.inactive{background:#EFEAE4;color:var(--mute)}

/* ================= CART / CHECKOUT ================= */
.two-col{display:grid;grid-template-columns:1.65fr 1fr;gap:26px;align-items:start}
.cart-row{display:grid;grid-template-columns:96px 1fr auto;gap:18px;padding:20px 0;border-bottom:1px solid var(--line);align-items:center}
.cart-row:last-child{border-bottom:none}
.cart-row img{width:96px;height:96px;object-fit:cover;border-radius:var(--r-s);background:var(--sand)}
.qty{display:inline-flex;align-items:center;border:1px solid var(--line);border-radius:999px;overflow:hidden;background:#fff}
.qty button{width:34px;height:34px;border:none;background:none;cursor:pointer;color:var(--ink);font-size:16px;transition:.2s}
.qty button:hover{background:var(--sand);color:var(--clay)}
.qty input{width:44px;text-align:center;border:none;background:none;font-family:'Jost';font-size:14px;padding:0;border-radius:0}
.qty input:focus{box-shadow:none}
.sum-row{display:flex;justify-content:space-between;padding:11px 0;font-size:14.5px;border-bottom:1px dashed var(--line)}
.sum-row.total{border-bottom:none;padding-top:16px;font-size:19px;font-family:'Cormorant Garamond',serif;color:var(--ink);font-weight:600}
.ship-bar{height:6px;border-radius:4px;background:var(--sand);overflow:hidden;margin:8px 0 6px}
.ship-bar i{display:block;height:100%;background:var(--moss);border-radius:4px;transition:width .7s var(--ease)}

/* pay methods */
.pay-opt{display:flex;gap:14px;align-items:flex-start;border:1px solid var(--line);border-radius:var(--r-m);padding:16px 18px;cursor:pointer;transition:.3s var(--ease);background:#fff;margin-bottom:12px}
.pay-opt:hover{border-color:var(--clay);transform:translateX(4px)}
.pay-opt input{margin-top:4px;accent-color:var(--clay);width:17px;height:17px}
.pay-opt.on{border-color:var(--clay);background:#FFF9F5;box-shadow:0 0 0 3px rgba(185,96,63,.1)}
.pay-opt b{display:block;color:var(--ink);font-weight:500;font-size:15px}
.pay-opt span{font-size:13px;color:var(--mute)}

/* steps */
.steps{display:flex;align-items:center;gap:0;margin-bottom:34px}
.step{flex:1;display:flex;flex-direction:column;align-items:center;gap:8px;position:relative}
.step .dot{width:34px;height:34px;border-radius:50%;background:#fff;border:1.5px solid var(--line);display:grid;place-items:center;font-size:13px;color:var(--mute);z-index:2;transition:.4s}
.step .bar{position:absolute;top:17px;left:50%;width:100%;height:1.5px;background:var(--line);z-index:1}
.step:last-child .bar{display:none}
.step .lb{font-size:11px;letter-spacing:.12em;text-transform:uppercase;color:var(--mute)}
.step.done .dot,.step.on .dot{background:var(--clay);border-color:var(--clay);color:#fff}
.step.done .bar{background:var(--clay)}
.step.on .lb,.step.done .lb{color:var(--ink)}

/* success */
.tick{width:86px;height:86px;border-radius:50%;background:var(--moss-lt);display:grid;place-items:center;margin:0 auto 20px;animation:pop .6s var(--ease) both}
.tick svg{width:40px;height:40px;stroke:var(--moss);fill:none;stroke-width:2.4;stroke-linecap:round;stroke-linejoin:round;stroke-dasharray:60;stroke-dashoffset:60;animation:draw .7s .3s var(--ease) forwards}
@keyframes pop{from{transform:scale(.4);opacity:0}to{transform:scale(1);opacity:1}}
@keyframes draw{to{stroke-dashoffset:0}}

/* ================= PRODUCT DETAIL ================= */
.pd{display:grid;grid-template-columns:1fr 1fr;gap:46px;align-items:start}
.pd-media{border-radius:var(--r-l);overflow:hidden;background:var(--sand);position:relative;box-shadow:var(--sh-1)}
.pd-media img{width:100%;aspect-ratio:1/1;object-fit:cover;transition:.6s var(--ease)}
.pd-media:hover img{transform:scale(1.05)}
.pd h1{font-size:clamp(30px,4vw,46px);margin:10px 0 12px}
.pd-price{display:flex;align-items:baseline;gap:12px;margin:18px 0}
.pd-price .now{font-family:'Cormorant Garamond',serif;font-size:38px;color:var(--ink);font-weight:600}
.pd-price .was{font-size:17px;color:var(--mute);text-decoration:line-through}
.pill{display:inline-block;font-size:11px;letter-spacing:.1em;text-transform:uppercase;background:var(--clay-lt);color:var(--clay-dk);padding:5px 12px;border-radius:999px}
.spec-list{list-style:none;display:flex;flex-direction:column;gap:9px;margin-top:8px}
.spec-list li{display:flex;gap:10px;font-size:14px;padding-bottom:9px;border-bottom:1px dashed var(--line)}
.spec-list li b{color:var(--ink);font-weight:500;min-width:150px}
.tabs-h{display:flex;gap:26px;border-bottom:1px solid var(--line);margin:34px 0 20px}
.tabs-h button{background:none;border:none;font-family:'Jost';font-size:13px;letter-spacing:.12em;text-transform:uppercase;color:var(--mute);padding:12px 0;cursor:pointer;position:relative}
.tabs-h button.on{color:var(--ink)}
.tabs-h button::after{content:"";position:absolute;left:0;bottom:-1px;height:2px;width:100%;background:var(--clay);transform:scaleX(0);transition:.35s var(--ease)}
.tabs-h button.on::after{transform:scaleX(1)}
.tabpane{display:none;animation:fadeIn .4s var(--ease)}
.tabpane.on{display:block}
@keyframes fadeIn{from{opacity:0;transform:translateY(8px)}to{opacity:1;transform:translateY(0)}}

/* ================= FILTER BAR ================= */
.filters{display:flex;gap:12px;flex-wrap:wrap;align-items:center;background:#fff;border:1px solid var(--line);border-radius:var(--r-m);padding:14px 16px;margin-bottom:28px}
.filters select,.filters input{width:auto;min-width:150px;padding:10px 12px;font-size:14px}
.filters .count{margin-left:auto;font-size:13px;color:var(--mute)}
.chipbar{display:flex;gap:9px;flex-wrap:wrap;margin-bottom:22px}
.chip{font-size:12.5px;letter-spacing:.06em;padding:9px 18px;border-radius:999px;border:1px solid var(--line);background:#fff;color:var(--ink-2);transition:.3s var(--ease)}
.chip:hover{border-color:var(--clay);transform:translateY(-2px)}
.chip.on{background:var(--ink);color:#fff;border-color:var(--ink)}

/* ================= COUPONS ================= */
.cpn-box{border:1px dashed var(--clay);border-radius:var(--r-m);padding:16px 18px;background:#FFF9F5;margin:4px 0 16px}
.cpn-form{display:flex;gap:8px;flex-wrap:wrap}
.cpn-form input{flex:1;min-width:140px;text-transform:uppercase;letter-spacing:.09em;font-size:14px;padding:11px 13px}
.cpn-on{display:flex;gap:12px;align-items:flex-start;justify-content:space-between;flex-wrap:wrap}
.cpn-on .tick-s{width:26px;height:26px;border-radius:50%;background:var(--moss-lt);color:var(--moss);display:grid;place-items:center;font-size:14px;flex:none}
.cpn-code{font-family:'Jost';font-weight:600;letter-spacing:.14em;color:var(--clay-dk);font-size:15px}
.cpn-list{display:flex;flex-direction:column;gap:9px;margin-top:14px}
.cpn-item{display:flex;gap:12px;align-items:center;justify-content:space-between;border:1px solid var(--line);border-radius:var(--r-s);background:#fff;padding:11px 13px;flex-wrap:wrap}
.cpn-item .cd{font-family:'Jost';font-weight:600;letter-spacing:.13em;font-size:13.5px;color:var(--ink);border:1px dashed var(--line);border-radius:6px;padding:5px 10px;background:var(--sand)}
.cpn-item small{display:block;color:var(--mute);font-size:12px;margin-top:2px}
.cpn-toggle{background:none;border:none;color:var(--clay-dk);font-family:'Jost';font-size:12.5px;letter-spacing:.09em;text-transform:uppercase;cursor:pointer;padding:0;margin-top:12px;text-decoration:underline}
.cpn-saved{color:var(--moss);font-size:13px;margin-top:6px}
.stock-warn{display:flex;gap:9px;align-items:flex-start;background:var(--danger-lt);color:var(--danger);border-left:3px solid var(--danger);border-radius:var(--r-s);padding:10px 13px;font-size:13px;margin-top:10px}
.stock-low{font-size:12.5px;color:var(--gold-dk,#8A6212);margin-top:8px}

/* ================= EMPTY / ALERTS ================= */
.empty{text-align:center;padding:78px 20px}
.empty svg{width:52px;height:52px;stroke:var(--line);fill:none;stroke-width:1.3;margin-bottom:14px}
.alert{padding:13px 18px;border-radius:var(--r-s);font-size:14px;margin-bottom:14px;border-left:3px solid}
.alert.success{background:var(--moss-lt);color:var(--moss);border-color:var(--moss)}
.alert.error{background:var(--danger-lt);color:var(--danger);border-color:var(--danger)}

/* ================= TOASTS ================= */
.toasts{position:fixed;top:22px;right:22px;z-index:300;display:flex;flex-direction:column;gap:10px}
.toast{background:var(--ink);color:#F0E5DA;padding:14px 20px;border-radius:var(--r-s);font-size:14px;box-shadow:var(--sh-2);display:flex;align-items:center;gap:10px;animation:tin .4s var(--ease) both;max-width:330px}
.toast.err{background:var(--danger)}
.toast b{color:var(--clay-lt);font-size:16px}
.toast.out{animation:tout .35s var(--ease) forwards}
@keyframes tin{from{opacity:0;transform:translateX(40px)}to{opacity:1;transform:translateX(0)}}
@keyframes tout{to{opacity:0;transform:translateX(40px)}}

/* ================= QUICK VIEW MODAL ================= */
.modal-bg{position:fixed;inset:0;background:rgba(36,28,25,.62);backdrop-filter:blur(4px);z-index:200;display:none;align-items:center;justify-content:center;padding:24px;overflow-y:auto}
.modal-bg.on{display:flex}
.modal{background:var(--cream);border-radius:var(--r-l);max-width:820px;width:100%;position:relative;animation:min .4s var(--ease) both;overflow:hidden}
@keyframes min{from{opacity:0;transform:translateY(24px) scale(.97)}to{opacity:1;transform:none}}
.modal-x{position:absolute;top:16px;right:16px;width:38px;height:38px;border-radius:50%;border:none;background:rgba(255,255,255,.9);cursor:pointer;font-size:19px;color:var(--ink);z-index:4;transition:.25s}
.modal-x:hover{background:var(--clay);color:#fff;transform:rotate(90deg)}

/* ================= REVEAL ON SCROLL ================= */
.rv{opacity:0;transform:translateY(26px);transition:opacity .8s var(--ease),transform .8s var(--ease)}
.rv.in{opacity:1;transform:none}

/* ================= ADMIN ================= */
.adm{display:flex;min-height:100vh;background:#F6F2ED}
.side{width:250px;background:#1E1815;color:#B9A99C;padding:22px 0;position:sticky;top:0;height:100vh;overflow-y:auto;flex:none}
.side .brand{padding:0 22px 22px;border-bottom:1px solid rgba(255,255,255,.08);margin-bottom:16px}
.side .brand .logo{color:#fff;font-size:25px}
.side .brand small{color:#8C7C70}
.side a{display:flex;align-items:center;gap:12px;padding:12px 22px;font-size:14px;transition:.25s;border-left:3px solid transparent}
.side a:hover{background:rgba(255,255,255,.05);color:#fff}
.side a.on{background:rgba(185,96,63,.16);color:#fff;border-left-color:var(--clay)}
.side svg{width:17px;height:17px;stroke:currentColor;fill:none;stroke-width:1.6;stroke-linecap:round}
.side .grp{padding:16px 22px 7px;font-size:10.5px;letter-spacing:.16em;text-transform:uppercase;color:#6E6055}
.adm-main{flex:1;min-width:0}
.adm-top{background:#fff;border-bottom:1px solid var(--line);padding:16px 28px;display:flex;align-items:center;gap:16px;position:sticky;top:0;z-index:20}
.adm-top h2{font-size:26px}
.adm-body{padding:28px}
.kpis{display:grid;grid-template-columns:repeat(auto-fit,minmax(190px,1fr));gap:16px;margin-bottom:24px}
.kpi{background:#fff;border:1px solid var(--line);border-radius:var(--r-m);padding:20px;display:flex;gap:15px;align-items:center;transition:.35s var(--ease)}
.kpi:hover{transform:translateY(-4px);box-shadow:var(--sh-1)}
.kpi .ic{width:46px;height:46px;border-radius:12px;display:grid;place-items:center;flex:none}
.kpi .ic svg{width:21px;height:21px;stroke-width:1.7;fill:none;stroke-linecap:round;stroke-linejoin:round}
.kpi b{display:block;font-family:'Cormorant Garamond',serif;font-size:31px;color:var(--ink);line-height:1.1;font-variant-numeric:tabular-nums}
.kpi span{font-size:12px;letter-spacing:.1em;text-transform:uppercase;color:var(--mute)}
.adm-card{background:#fff;border:1px solid var(--line);border-radius:var(--r-m);padding:22px;margin-bottom:22px}
.adm-card h3{font-size:20px;margin-bottom:16px}
.adm-grid2{display:grid;grid-template-columns:1.5fr 1fr;gap:18px}
.row-acts{display:flex;gap:7px;flex-wrap:wrap}
.mini{padding:7px 13px;font-size:11.5px;border-radius:var(--r-s);border:1px solid var(--line);background:#fff;color:var(--ink-2);cursor:pointer;transition:.25s;text-transform:uppercase;letter-spacing:.06em;font-family:'Jost'}
.mini:hover{border-color:var(--clay);color:var(--clay);transform:translateY(-1px)}
.mini.del:hover{border-color:var(--danger);color:var(--danger)}
.login-bg{min-height:100vh;display:grid;place-items:center;background:linear-gradient(140deg,#1E1815,#3A2A22 70%,var(--clay-dk));padding:24px}
.login-card{background:var(--cream);border-radius:var(--r-l);padding:40px;width:100%;max-width:420px;box-shadow:var(--sh-3);animation:min .5s var(--ease) both}

/* ================= RESPONSIVE ================= */
@media(max-width:1000px){
  .two-col,.pd,.adm-grid2{grid-template-columns:1fr}
  .f-grid{grid-template-columns:1fr 1fr}
  .side{position:fixed;left:-250px;z-index:90;transition:.35s var(--ease)}
  .side.open{left:0}
  .burger{display:grid}
}
@media(max-width:820px){
  .menu{position:fixed;inset:0 0 0 auto;width:280px;background:var(--cream);flex-direction:column;padding:96px 28px;gap:20px;transform:translateX(100%);transition:.4s var(--ease);box-shadow:var(--sh-3);z-index:70;margin:0}
  .menu.open{transform:translateX(0)}
  .menu a{font-size:16px}
  .burger{display:grid}
  .searchbar{display:none}
  .fgrid{grid-template-columns:1fr}
  .offer{padding:36px 26px}
  .offer .right{margin-left:0}
  .hero{height:78vh}
  .hero-arrow{display:none}
  .sec{padding:56px 0}
}
@media(max-width:560px){
  .f-grid{grid-template-columns:1fr}
  .cart-row{grid-template-columns:72px 1fr}
  .cart-row img{width:72px;height:72px}
  .p-grid{grid-template-columns:repeat(auto-fill,minmax(160px,1fr));gap:16px}
  .p-body{padding:14px}
  .p-title{font-size:16.5px}
  .news,.panel{padding:24px 18px}
  .adm-body{padding:16px}
}
@media(prefers-reduced-motion:reduce){*{animation-duration:.01ms!important;transition-duration:.01ms!important}}
</style>
</head>
<body>

<?php /* =================== TOASTS =================== */ ?>
<div class="toasts" id="toasts">
<?php foreach ($flashes as $f): ?>
  <div class="toast <?= $f['t'] === 'error' ? 'err' : '' ?>"><b><?= $f['t'] === 'error' ? '!' : '✓' ?></b><span><?= e($f['m']) ?></span></div>
<?php endforeach; ?>
</div>

<?php
/* ======================================================================
   ADMIN LOGIN PAGE (no chrome)
   ====================================================================== */
if ($page === 'admin_login'):
    if (current_admin()) redirect(url(['page' => 'admin_dashboard']));
?>
<div class="login-bg">
  <div class="login-card">
    <div class="center" style="margin-bottom:26px">
      <div class="logo" style="font-size:36px"><?= e(SITE_NAME) ?><small>Admin Console</small></div>
    </div>
    <form method="post">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="admin_login">
      <div class="fld" style="margin-bottom:16px">
        <label>Username</label>
        <input type="text" name="username" value="admin" required autofocus>
      </div>
      <div class="fld" style="margin-bottom:22px">
        <label>Password</label>
        <input type="password" name="password" value="" placeholder="admin123" required>
      </div>
      <button class="btn wide"><span>Sign in to dashboard</span></button>
    </form>
    <p class="hint center" style="margin-top:20px">Default credentials — <b>admin</b> / <b>admin123</b></p>
    <p class="center" style="margin-top:12px"><a class="link-u" href="<?= url(['page' => 'home']) ?>">← Back to store</a></p>
  </div>
</div>
<script>
setTimeout(()=>document.querySelectorAll('.toast').forEach(t=>{t.classList.add('out');setTimeout(()=>t.remove(),350)}),3600);
</script>
</body></html>
<?php
    exit;
endif;

/* ======================================================================
   ADMIN AREA
   ====================================================================== */
if ($is_admin):
    require_admin();
    $admin = current_admin();
    $titles = [
        'admin_dashboard'  => 'Dashboard',
        'admin_users'      => 'Customers',
        'admin_categories' => 'Categories',
        'admin_products'   => 'Products',
        'admin_orders'     => 'Orders',
        'admin_order_view' => 'Order Detail',
        'admin_coupons'    => 'Coupons',
        'admin_messages'   => 'Messages',
    ];
    $unread = (int)db()->query('SELECT COUNT(*) FROM contacts WHERE is_read=0')->fetchColumn();
?>
<div class="adm">
  <aside class="side" id="side">
    <div class="brand"><div class="logo"><?= e(SITE_NAME) ?><small>Admin Console</small></div></div>
    <div class="grp">Overview</div>
    <a href="<?= url(['page' => 'admin_dashboard']) ?>" class="<?= $page === 'admin_dashboard' ? 'on' : '' ?>">
      <svg viewBox="0 0 24 24"><rect x="3" y="3" width="7" height="9"/><rect x="14" y="3" width="7" height="5"/><rect x="14" y="12" width="7" height="9"/><rect x="3" y="16" width="7" height="5"/></svg> Dashboard</a>
    <div class="grp">Catalogue</div>
    <a href="<?= url(['page' => 'admin_categories']) ?>" class="<?= $page === 'admin_categories' ? 'on' : '' ?>">
      <svg viewBox="0 0 24 24"><path d="M3 7h18M3 12h18M3 17h18"/></svg> Categories</a>
    <a href="<?= url(['page' => 'admin_products']) ?>" class="<?= $page === 'admin_products' ? 'on' : '' ?>">
      <svg viewBox="0 0 24 24"><path d="M20 7l-8-4-8 4 8 4 8-4z"/><path d="M4 7v10l8 4 8-4V7"/></svg> Products</a>
    <div class="grp">Sales</div>
    <a href="<?= url(['page' => 'admin_orders']) ?>" class="<?= in_array($page, ['admin_orders', 'admin_order_view'], true) ? 'on' : '' ?>">
      <svg viewBox="0 0 24 24"><path d="M6 2l1.5 3h9L18 2"/><rect x="3" y="5" width="18" height="16" rx="2"/><path d="M8 10h8"/></svg> Orders</a>
    <a href="<?= url(['page' => 'admin_coupons']) ?>" class="<?= $page === 'admin_coupons' ? 'on' : '' ?>">
      <svg viewBox="0 0 24 24"><path d="M3 9V7a2 2 0 012-2h14a2 2 0 012 2v2a2 2 0 000 6v2a2 2 0 01-2 2H5a2 2 0 01-2-2v-2a2 2 0 000-6z"/><path d="M14 8l-4 8"/></svg> Coupons</a>
    <a href="<?= url(['page' => 'admin_users']) ?>" class="<?= $page === 'admin_users' ? 'on' : '' ?>">
      <svg viewBox="0 0 24 24"><circle cx="12" cy="8" r="4"/><path d="M4 21c0-4 3.6-7 8-7s8 3 8 7"/></svg> Customers</a>
    <a href="<?= url(['page' => 'admin_messages']) ?>" class="<?= $page === 'admin_messages' ? 'on' : '' ?>">
      <svg viewBox="0 0 24 24"><rect x="3" y="5" width="18" height="14" rx="2"/><path d="M3 7l9 6 9-6"/></svg>
      Messages <?php if ($unread): ?><span class="badge" style="position:static;margin-left:auto"><?= $unread ?></span><?php endif; ?></a>
    <div class="grp">Account</div>
    <a href="<?= url(['page' => 'home']) ?>" target="_blank">
      <svg viewBox="0 0 24 24"><path d="M18 13v6a2 2 0 01-2 2H5a2 2 0 01-2-2V8a2 2 0 012-2h6"/><path d="M15 3h6v6M10 14L21 3"/></svg> View store</a>
    <form method="post" style="padding:0 22px;margin-top:10px">
      <?= csrf_field() ?><input type="hidden" name="action" value="admin_logout">
      <button class="mini del" style="width:100%">Log out</button>
    </form>
  </aside>

  <div class="adm-main">
    <div class="adm-top">
      <button class="ico burger" id="sideToggle"><svg viewBox="0 0 24 24"><path d="M3 6h18M3 12h18M3 18h18"/></svg></button>
      <h2><?= e($titles[$page] ?? 'Admin') ?></h2>
      <div style="margin-left:auto;display:flex;align-items:center;gap:12px">
        <span class="hint"><?= date('d M Y') ?></span>
        <div style="width:38px;height:38px;border-radius:50%;background:var(--clay);color:#fff;display:grid;place-items:center;font-weight:600">
          <?= e(strtoupper(substr($admin['full_name'], 0, 1))) ?>
        </div>
      </div>
    </div>
    <div class="adm-body">

<?php
/* ---------------------- ADMIN: DASHBOARD ---------------------- */
if ($page === 'admin_dashboard'):
    $db = db();
    $totUsers  = (int)$db->query('SELECT COUNT(*) FROM users')->fetchColumn();
    $totCats   = (int)$db->query('SELECT COUNT(*) FROM categories')->fetchColumn();
    $totProds  = (int)$db->query('SELECT COUNT(*) FROM products')->fetchColumn();
    $totOrders = (int)$db->query('SELECT COUNT(*) FROM orders')->fetchColumn();
    $revenue   = (float)$db->query("SELECT COALESCE(SUM(total),0) FROM orders WHERE order_status<>'Cancelled'")->fetchColumn();
    $pending   = (int)$db->query("SELECT COUNT(*) FROM orders WHERE order_status='Pending'")->fetchColumn();
    $lowStock  = $db->query('SELECT id,name,stock FROM products WHERE stock<=10 ORDER BY stock ASC LIMIT 6')->fetchAll();
    $recent    = $db->query('SELECT * FROM orders ORDER BY id DESC LIMIT 7')->fetchAll();
    $byStatus  = [];
    foreach ($db->query('SELECT order_status s, COUNT(*) c FROM orders GROUP BY order_status') as $r) $byStatus[$r['s']] = (int)$r['c'];

    $days = []; $rev = [];
    for ($i = 6; $i >= 0; $i--) {
        $d = date('Y-m-d', strtotime("-$i day"));
        $days[] = date('d M', strtotime($d));
        $st = $db->prepare("SELECT COALESCE(SUM(total),0) FROM orders WHERE DATE(created_at)=? AND order_status<>'Cancelled'");
        $st->execute([$d]);
        $rev[] = (float)$st->fetchColumn();
    }
    $topProd = $db->query('SELECT product_name, SUM(qty) q, SUM(line_total) amt FROM order_items
                           GROUP BY product_name ORDER BY q DESC LIMIT 5')->fetchAll();
?>
      <div class="kpis">
        <div class="kpi"><div class="ic" style="background:var(--clay-lt);color:var(--clay-dk)">
          <svg viewBox="0 0 24 24"><circle cx="12" cy="8" r="4"/><path d="M4 21c0-4 3.6-7 8-7s8 3 8 7"/></svg></div>
          <div><b><?= $totUsers ?></b><span>Total Users</span></div></div>
        <div class="kpi"><div class="ic" style="background:#E7EDF5;color:#3B5A80">
          <svg viewBox="0 0 24 24"><path d="M3 7h18M3 12h18M3 17h18"/></svg></div>
          <div><b><?= $totCats ?></b><span>Categories</span></div></div>
        <div class="kpi"><div class="ic" style="background:var(--gold-lt);color:#8A6212">
          <svg viewBox="0 0 24 24"><path d="M20 7l-8-4-8 4 8 4 8-4z"/><path d="M4 7v10l8 4 8-4V7"/></svg></div>
          <div><b><?= $totProds ?></b><span>Products</span></div></div>
        <div class="kpi"><div class="ic" style="background:#E9E6F4;color:#5B4B92">
          <svg viewBox="0 0 24 24"><rect x="3" y="5" width="18" height="16" rx="2"/><path d="M8 10h8"/></svg></div>
          <div><b><?= $totOrders ?></b><span>Total Orders</span></div></div>
        <div class="kpi"><div class="ic" style="background:var(--moss-lt);color:var(--moss)">
          <svg viewBox="0 0 24 24"><path d="M12 1v22"/><path d="M17 5H9.5a3.5 3.5 0 000 7h5a3.5 3.5 0 010 7H6"/></svg></div>
          <div><b style="font-size:26px"><?= CURRENCY . number_format($revenue) ?></b><span>Total Revenue</span></div></div>
      </div>

      <div class="adm-grid2">
        <div class="adm-card">
          <h3>Revenue — last 7 days</h3>
          <canvas id="revChart" height="110"></canvas>
        </div>
        <div class="adm-card">
          <h3>Orders by status</h3>
          <canvas id="stChart" height="110"></canvas>
        </div>
      </div>

      <div class="adm-grid2">
        <div class="adm-card">
          <h3>Recent orders <?php if ($pending): ?><span class="stx pending" style="margin-left:8px"><?= $pending ?> pending</span><?php endif; ?></h3>
          <div style="overflow-x:auto">
          <table class="tbl">
            <thead><tr><th>Order</th><th>Customer</th><th>Total</th><th>Status</th><th></th></tr></thead>
            <tbody>
            <?php if (!$recent): ?>
              <tr><td colspan="5" class="hint">No orders yet.</td></tr>
            <?php else: foreach ($recent as $o): ?>
              <tr>
                <td><b>#<?= (int)$o['id'] ?></b><div class="hint"><?= date('d M, H:i', strtotime($o['created_at'])) ?></div></td>
                <td><?= e($o['customer_name']) ?></td>
                <td><?= money($o['total']) ?></td>
                <td><span class="<?= status_class($o['order_status']) ?>"><?= e($o['order_status']) ?></span></td>
                <td><a class="mini" href="<?= url(['page' => 'admin_order_view', 'id' => $o['id']]) ?>">View</a></td>
              </tr>
            <?php endforeach; endif; ?>
            </tbody>
          </table>
          </div>
        </div>

        <div class="adm-card">
          <h3>Low stock alert</h3>
          <?php if (!$lowStock): ?><p class="hint">All products are comfortably stocked.</p><?php else: ?>
            <?php foreach ($lowStock as $ls): ?>
              <div style="display:flex;justify-content:space-between;align-items:center;padding:10px 0;border-bottom:1px dashed var(--line)">
                <span style="font-size:14px"><?= e($ls['name']) ?></span>
                <span class="stx <?= (int)$ls['stock'] === 0 ? 'cancelled' : 'pending' ?>"><?= (int)$ls['stock'] ?> left</span>
              </div>
            <?php endforeach; ?>
          <?php endif; ?>
          <h3 style="margin-top:24px">Best sellers</h3>
          <?php if (!$topProd): ?><p class="hint">No sales recorded yet.</p><?php else: foreach ($topProd as $i => $tp): ?>
            <div style="display:flex;justify-content:space-between;align-items:center;padding:10px 0;border-bottom:1px dashed var(--line)">
              <span style="font-size:14px"><b style="color:var(--clay);margin-right:8px"><?= $i + 1 ?></b><?= e($tp['product_name']) ?></span>
              <span class="hint"><?= (int)$tp['q'] ?> sold</span>
            </div>
          <?php endforeach; endif; ?>
        </div>
      </div>

      <script>
      (function(){
        const rc=document.getElementById('revChart');
        new Chart(rc,{type:'line',data:{labels:<?= json_encode($days) ?>,
          datasets:[{label:'Revenue',data:<?= json_encode($rev) ?>,borderColor:'#B9603F',
          backgroundColor:'rgba(185,96,63,.14)',fill:true,tension:.38,pointRadius:4,pointBackgroundColor:'#B9603F'}]},
          options:{plugins:{legend:{display:false}},scales:{y:{beginAtZero:true,grid:{color:'#EDE3D8'}},x:{grid:{display:false}}}}});
        const sc=document.getElementById('stChart');
        new Chart(sc,{type:'doughnut',data:{labels:<?= json_encode(array_keys($byStatus) ?: ['No orders']) ?>,
          datasets:[{data:<?= json_encode(array_values($byStatus) ?: [1]) ?>,
          backgroundColor:['#C08B2E','#B9603F','#3B5A80','#5B4B92','#3E5C47','#A63A2E']}]},
          options:{cutout:'62%',plugins:{legend:{position:'bottom',labels:{boxWidth:12,font:{size:11}}}}}});
      })();
      </script>

<?php
/* ---------------------- ADMIN: CATEGORIES ---------------------- */
elseif ($page === 'admin_categories'):
    $edit = null;
    if ($eid = (int)get('edit', 0)) {
        $st = db()->prepare('SELECT * FROM categories WHERE id=?'); $st->execute([$eid]); $edit = $st->fetch();
    }
    $cats = all_categories(false);
?>
      <div class="adm-grid2">
        <div class="adm-card">
          <h3><?= $edit ? 'Edit category' : 'Add new category' ?></h3>
          <form method="post" enctype="multipart/form-data">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="cat_save">
            <input type="hidden" name="id" value="<?= (int)($edit['id'] ?? 0) ?>">
            <div class="fgrid">
              <div class="fld full"><label>Category name</label>
                <input type="text" name="name" required value="<?= e($edit['name'] ?? '') ?>"></div>
              <div class="fld full"><label>Image URL</label>
                <input type="text" name="image" placeholder="https://…" value="<?= e($edit['image'] ?? '') ?>"></div>
              <div class="fld full"><label>…or upload an image</label>
                <input type="file" name="image_file" accept="image/*"></div>
              <div class="fld full"><label>Description</label>
                <textarea name="description"><?= e($edit['description'] ?? '') ?></textarea></div>
              <div class="fld full"><label>Status</label>
                <select name="status">
                  <option value="1" <?= (int)($edit['status'] ?? 1) === 1 ? 'selected' : '' ?>>Active</option>
                  <option value="0" <?= isset($edit) && (int)$edit['status'] === 0 ? 'selected' : '' ?>>Inactive</option>
                </select></div>
            </div>
            <div style="margin-top:18px;display:flex;gap:10px">
              <button class="btn sm"><span><?= $edit ? 'Update' : 'Add category' ?></span></button>
              <?php if ($edit): ?><a class="btn sm ghost" href="<?= url(['page' => 'admin_categories']) ?>"><span>Cancel</span></a><?php endif; ?>
            </div>
          </form>
        </div>

        <div class="adm-card">
          <h3>All categories (<?= count($cats) ?>)</h3>
          <div style="overflow-x:auto">
          <table class="tbl">
            <thead><tr><th>ID</th><th>Image</th><th>Name</th><th>Products</th><th>Status</th><th>Actions</th></tr></thead>
            <tbody>
            <?php foreach ($cats as $c): ?>
              <tr>
                <td>#<?= (int)$c['id'] ?></td>
                <td><img class="thumb" src="<?= img_src($c['image'], $c['name'] ?? '', $c['slug'] ?? '') ?>" alt="" onerror="this.onerror=null;this.src='<?= e(art_fallback($c['name'] ?? '', $c['slug'] ?? '')) ?>';"></td>
                <td><b><?= e($c['name']) ?></b><div class="hint"><?= e(mb_substr((string)$c['description'], 0, 46)) ?>…</div></td>
                <td><?= (int)$c['product_count'] ?></td>
                <td><span class="<?= status_class((int)$c['status'] === 1 ? 'Active' : 'Inactive') ?>"><?= (int)$c['status'] === 1 ? 'Active' : 'Inactive' ?></span></td>
                <td><div class="row-acts">
                  <a class="mini" href="<?= url(['page' => 'admin_categories', 'edit' => $c['id']]) ?>">Edit</a>
                  <form method="post" onsubmit="return confirm('Delete this category?')">
                    <?= csrf_field() ?><input type="hidden" name="action" value="cat_delete"><input type="hidden" name="id" value="<?= (int)$c['id'] ?>">
                    <button class="mini del">Delete</button>
                  </form>
                </div></td>
              </tr>
            <?php endforeach; ?>
            </tbody>
          </table>
          </div>
        </div>
      </div>

<?php
/* ---------------------- ADMIN: PRODUCTS ---------------------- */
elseif ($page === 'admin_products'):
    $edit = null;
    if ($eid = (int)get('edit', 0)) { $edit = find_product($eid); }
    $cats = all_categories(false);
    $q    = trim((string)get('q', ''));
    $sql  = 'SELECT p.*, c.name cat FROM products p LEFT JOIN categories c ON c.id=p.category_id';
    $bind = [];
    if ($q !== '') { $sql .= ' WHERE p.name LIKE ?'; $bind[] = '%' . $q . '%'; }
    $sql .= ' ORDER BY p.id DESC';
    $st = db()->prepare($sql); $st->execute($bind); $prods = $st->fetchAll();
?>
      <div class="adm-card">
        <h3><?= $edit ? 'Edit product — ' . e($edit['name']) : 'Add new product' ?></h3>
        <form method="post" enctype="multipart/form-data">
          <?= csrf_field() ?>
          <input type="hidden" name="action" value="prod_save">
          <input type="hidden" name="id" value="<?= (int)($edit['id'] ?? 0) ?>">
          <div class="fgrid">
            <div class="fld"><label>Product name</label>
              <input type="text" name="name" required value="<?= e($edit['name'] ?? '') ?>"></div>
            <div class="fld"><label>Category</label>
              <select name="category_id" required>
                <?php foreach ($cats as $c): ?>
                  <option value="<?= (int)$c['id'] ?>" <?= isset($edit) && (int)$edit['category_id'] === (int)$c['id'] ? 'selected' : '' ?>><?= e($c['name']) ?></option>
                <?php endforeach; ?>
              </select></div>
            <div class="fld"><label>Price (<?= CURRENCY ?>)</label>
              <input type="number" step="0.01" name="price" required value="<?= e($edit['price'] ?? '') ?>"></div>
            <div class="fld"><label>Discount price <span class="hint">(0 = none)</span></label>
              <input type="number" step="0.01" name="discount" value="<?= e($edit['discount'] ?? '0') ?>"></div>
            <div class="fld"><label>Stock quantity</label>
              <input type="number" name="stock" required value="<?= e($edit['stock'] ?? '0') ?>"></div>
            <div class="fld"><label>Rating (1–5)</label>
              <input type="number" step="0.1" min="1" max="5" name="rating" value="<?= e($edit['rating'] ?? '4.5') ?>"></div>
            <div class="fld"><label>Image URL</label>
              <input type="text" name="image" placeholder="https://…" value="<?= e($edit['image'] ?? '') ?>"></div>
            <div class="fld"><label>…or upload image</label>
              <input type="file" name="image_file" accept="image/*"></div>
            <div class="fld full"><label>Short description</label>
              <input type="text" name="short_desc" maxlength="300" value="<?= e($edit['short_desc'] ?? '') ?>"></div>
            <div class="fld full"><label>Full description</label>
              <textarea name="description"><?= e($edit['description'] ?? '') ?></textarea></div>
            <div class="fld full"><label>Specifications <span class="hint">(separate with | )</span></label>
              <input type="text" name="specs" value="<?= e($edit['specs'] ?? '') ?>"></div>
            <div class="fld"><label>Featured on home page</label>
              <select name="is_featured">
                <option value="0" <?= isset($edit) && (int)$edit['is_featured'] === 0 ? 'selected' : '' ?>>No</option>
                <option value="1" <?= isset($edit) && (int)$edit['is_featured'] === 1 ? 'selected' : '' ?>>Yes</option>
              </select></div>
            <div class="fld"><label>Status</label>
              <select name="status">
                <option value="1" <?= (int)($edit['status'] ?? 1) === 1 ? 'selected' : '' ?>>Active</option>
                <option value="0" <?= isset($edit) && (int)$edit['status'] === 0 ? 'selected' : '' ?>>Inactive</option>
              </select></div>
          </div>
          <div style="margin-top:18px;display:flex;gap:10px">
            <button class="btn sm"><span><?= $edit ? 'Update product' : 'Add product' ?></span></button>
            <?php if ($edit): ?><a class="btn sm ghost" href="<?= url(['page' => 'admin_products']) ?>"><span>Cancel</span></a><?php endif; ?>
          </div>
        </form>
      </div>

      <div class="adm-card">
        <h3 style="display:flex;align-items:center;gap:14px;flex-wrap:wrap">All products (<?= count($prods) ?>)
          <form method="get" style="margin-left:auto;display:flex;gap:8px">
            <input type="hidden" name="page" value="admin_products">
            <input type="text" name="q" placeholder="Search products…" value="<?= e($q) ?>" style="width:220px">
            <button class="mini">Search</button>
          </form>
        </h3>
        <div style="overflow-x:auto">
        <table class="tbl">
          <thead><tr><th>ID</th><th>Image</th><th>Product</th><th>Category</th><th>Price</th><th>Stock</th><th>Status</th><th>Actions</th></tr></thead>
          <tbody>
          <?php if (!$prods): ?><tr><td colspan="8" class="hint">No products found.</td></tr><?php endif; ?>
          <?php foreach ($prods as $p): ?>
            <tr>
              <td>#<?= (int)$p['id'] ?></td>
              <td><img class="thumb" src="<?= img_src($p['image'], $p['name'] ?? '', '') ?>" alt="" onerror="this.onerror=null;this.src='<?= e(art_fallback($p['name'] ?? '', '')) ?>';"></td>
              <td><b><?= e($p['name']) ?></b>
                  <?php if ((int)$p['is_featured']): ?><span class="stx confirmed" style="margin-left:6px">Featured</span><?php endif; ?>
                  <div class="hint"><?= e(mb_substr((string)$p['short_desc'], 0, 48)) ?></div></td>
              <td><?= e($p['cat']) ?></td>
              <td><?= money(final_price($p)) ?>
                  <?php if (discount_percent($p)): ?><div class="hint" style="text-decoration:line-through"><?= money($p['price']) ?></div><?php endif; ?></td>
              <td><span class="stx <?= (int)$p['stock'] > 10 ? 'delivered' : ((int)$p['stock'] > 0 ? 'pending' : 'cancelled') ?>"><?= (int)$p['stock'] ?></span></td>
              <td><span class="<?= status_class((int)$p['status'] === 1 ? 'Active' : 'Inactive') ?>"><?= (int)$p['status'] === 1 ? 'Active' : 'Inactive' ?></span></td>
              <td><div class="row-acts">
                <a class="mini" href="<?= url(['page' => 'product', 'id' => $p['id']]) ?>" target="_blank">View</a>
                <a class="mini" href="<?= url(['page' => 'admin_products', 'edit' => $p['id']]) ?>">Edit</a>
                <form method="post" onsubmit="return confirm('Delete this product?')">
                  <?= csrf_field() ?><input type="hidden" name="action" value="prod_delete"><input type="hidden" name="id" value="<?= (int)$p['id'] ?>">
                  <button class="mini del">Delete</button>
                </form>
              </div></td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
        </div>
      </div>

<?php
/* ---------------------- ADMIN: USERS ---------------------- */
elseif ($page === 'admin_users'):
    $edit = null;
    if ($eid = (int)get('edit', 0)) { $st = db()->prepare('SELECT * FROM users WHERE id=?'); $st->execute([$eid]); $edit = $st->fetch(); }
    $q = trim((string)get('q', ''));
    $sql = 'SELECT u.*, (SELECT COUNT(*) FROM orders o WHERE o.user_id=u.id) orders_count,
            (SELECT COALESCE(SUM(total),0) FROM orders o WHERE o.user_id=u.id) spent FROM users u';
    $bind = [];
    if ($q !== '') { $sql .= ' WHERE u.name LIKE ? OR u.email LIKE ? OR u.mobile LIKE ?'; $bind = ["%$q%", "%$q%", "%$q%"]; }
    $sql .= ' ORDER BY u.id DESC';
    $st = db()->prepare($sql); $st->execute($bind); $users = $st->fetchAll();
?>
      <?php if ($edit): ?>
      <div class="adm-card">
        <h3>Edit customer — <?= e($edit['name']) ?></h3>
        <form method="post">
          <?= csrf_field() ?><input type="hidden" name="action" value="user_save"><input type="hidden" name="id" value="<?= (int)$edit['id'] ?>">
          <div class="fgrid">
            <div class="fld"><label>Name</label><input type="text" name="name" value="<?= e($edit['name']) ?>" required></div>
            <div class="fld"><label>Email</label><input type="email" name="email" value="<?= e($edit['email']) ?>" required></div>
            <div class="fld"><label>Mobile</label><input type="text" name="mobile" value="<?= e($edit['mobile']) ?>"></div>
            <div class="fld"><label>Status</label>
              <select name="status">
                <option value="1" <?= (int)$edit['status'] === 1 ? 'selected' : '' ?>>Active</option>
                <option value="0" <?= (int)$edit['status'] === 0 ? 'selected' : '' ?>>Deactivated</option>
              </select></div>
            <div class="fld full"><label>Address</label><input type="text" name="address" value="<?= e($edit['address']) ?>"></div>
            <div class="fld"><label>City</label><input type="text" name="city" value="<?= e($edit['city']) ?>"></div>
            <div class="fld"><label>State</label><input type="text" name="state" value="<?= e($edit['state']) ?>"></div>
            <div class="fld"><label>Pincode</label><input type="text" name="pincode" value="<?= e($edit['pincode']) ?>"></div>
          </div>
          <div style="margin-top:18px;display:flex;gap:10px">
            <button class="btn sm"><span>Save changes</span></button>
            <a class="btn sm ghost" href="<?= url(['page' => 'admin_users']) ?>"><span>Cancel</span></a>
          </div>
        </form>
      </div>
      <?php endif; ?>

      <div class="adm-card">
        <h3 style="display:flex;align-items:center;gap:14px;flex-wrap:wrap">Registered customers (<?= count($users) ?>)
          <form method="get" style="margin-left:auto;display:flex;gap:8px">
            <input type="hidden" name="page" value="admin_users">
            <input type="text" name="q" placeholder="Search name, email, mobile…" value="<?= e($q) ?>" style="width:250px">
            <button class="mini">Search</button>
          </form>
        </h3>
        <div style="overflow-x:auto">
        <table class="tbl">
          <thead><tr><th>ID</th><th>Customer</th><th>Mobile</th><th>Location</th><th>Orders</th><th>Spent</th><th>Status</th><th>Actions</th></tr></thead>
          <tbody>
          <?php if (!$users): ?><tr><td colspan="8" class="hint">No customers found.</td></tr><?php endif; ?>
          <?php foreach ($users as $u): ?>
            <tr>
              <td>#<?= (int)$u['id'] ?></td>
              <td><b><?= e($u['name']) ?></b><div class="hint"><?= e($u['email']) ?></div></td>
              <td><?= e($u['mobile']) ?></td>
              <td><?= e(trim($u['city'] . ', ' . $u['state'], ', ')) ?: '<span class="hint">—</span>' ?></td>
              <td><?= (int)$u['orders_count'] ?></td>
              <td><?= money($u['spent']) ?></td>
              <td><span class="<?= status_class((int)$u['status'] === 1 ? 'Active' : 'Inactive') ?>"><?= (int)$u['status'] === 1 ? 'Active' : 'Deactivated' ?></span></td>
              <td><div class="row-acts">
                <a class="mini" href="<?= url(['page' => 'admin_users', 'edit' => $u['id']]) ?>">Edit</a>
                <form method="post" onsubmit="return confirm('Delete this customer permanently?')">
                  <?= csrf_field() ?><input type="hidden" name="action" value="user_delete"><input type="hidden" name="id" value="<?= (int)$u['id'] ?>">
                  <button class="mini del">Delete</button>
                </form>
              </div></td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
        </div>
      </div>

<?php
/* ---------------------- ADMIN: ORDERS ---------------------- */
elseif ($page === 'admin_orders'):
    $fs   = (string)get('status', '');
    $sql  = 'SELECT o.*, (SELECT COALESCE(SUM(qty),0) FROM order_items i WHERE i.order_id=o.id) items FROM orders o';
    $bind = [];
    if ($fs !== '') { $sql .= ' WHERE o.order_status=?'; $bind[] = $fs; }
    $sql .= ' ORDER BY o.id DESC';
    $st = db()->prepare($sql); $st->execute($bind); $orders = $st->fetchAll();
    $STATUSES = ['Pending', 'Confirmed', 'Processing', 'Shipped', 'Delivered', 'Cancelled'];
?>
      <div class="chipbar">
        <a class="chip <?= $fs === '' ? 'on' : '' ?>" href="<?= url(['page' => 'admin_orders']) ?>">All orders</a>
        <?php foreach ($STATUSES as $s): ?>
          <a class="chip <?= $fs === $s ? 'on' : '' ?>" href="<?= url(['page' => 'admin_orders', 'status' => $s]) ?>"><?= e($s) ?></a>
        <?php endforeach; ?>
      </div>
      <div class="adm-card">
        <h3>Order summary (<?= count($orders) ?>)</h3>
        <div style="overflow-x:auto">
        <table class="tbl">
          <thead><tr><th>Order ID</th><th>Customer</th><th>Date</th><th>Items</th><th>Total</th><th>Payment</th><th>Pay status</th><th>Order status</th><th></th></tr></thead>
          <tbody>
          <?php if (!$orders): ?><tr><td colspan="9" class="hint">No orders in this view.</td></tr><?php endif; ?>
          <?php foreach ($orders as $o): ?>
            <tr>
              <td><b>#<?= (int)$o['id'] ?></b></td>
              <td><?= e($o['customer_name']) ?><div class="hint"><?= e($o['email']) ?></div></td>
              <td><?= date('d M Y', strtotime($o['created_at'])) ?><div class="hint"><?= date('H:i', strtotime($o['created_at'])) ?></div></td>
              <td><?= (int)$o['items'] ?></td>
              <td><b><?= money($o['total']) ?></b></td>
              <td><?= e($o['payment_method']) ?></td>
              <td><span class="<?= status_class($o['payment_status']) ?>"><?= e($o['payment_status']) ?></span></td>
              <td><span class="<?= status_class($o['order_status']) ?>"><?= e($o['order_status']) ?></span></td>
              <td><a class="mini" href="<?= url(['page' => 'admin_order_view', 'id' => $o['id']]) ?>">Manage</a></td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
        </div>
      </div>

<?php
/* ---------------------- ADMIN: ORDER DETAIL ---------------------- */
elseif ($page === 'admin_order_view'):
    $o = order_with_items((int)get('id', 0));
    if (!$o):
        echo '<div class="adm-card"><p>Order not found.</p></div>';
    else:
    $STATUSES = ['Pending', 'Confirmed', 'Processing', 'Shipped', 'Delivered', 'Cancelled'];
    $PAYSTAT  = ['Pending', 'Paid', 'Failed', 'Refunded'];
?>
      <a class="mini" href="<?= url(['page' => 'admin_orders']) ?>" style="display:inline-block;margin-bottom:16px">← Back to orders</a>
      <div class="adm-grid2">
        <div class="adm-card">
          <h3>Order #<?= (int)$o['id'] ?> <span class="<?= status_class($o['order_status']) ?>" style="margin-left:8px"><?= e($o['order_status']) ?></span></h3>
          <p class="hint" style="margin-bottom:16px">Placed on <?= date('d M Y \a\t H:i', strtotime($o['created_at'])) ?></p>
          <table class="tbl">
            <thead><tr><th>Product</th><th>Price</th><th>Qty</th><th>Total</th></tr></thead>
            <tbody>
            <?php foreach ($o['items'] as $it): ?>
              <tr>
                <td style="display:flex;gap:12px;align-items:center">
                  <img class="thumb" src="<?= img_src($it['product_image'], $it['product_name'] ?? '', '') ?>" alt="" onerror="this.onerror=null;this.src='<?= e(art_fallback($it['product_name'] ?? '', '')) ?>';">
                  <span><?= e($it['product_name']) ?></span></td>
                <td><?= money($it['price']) ?></td>
                <td><?= (int)$it['qty'] ?></td>
                <td><b><?= money($it['line_total']) ?></b></td>
              </tr>
            <?php endforeach; ?>
            </tbody>
          </table>
          <div style="margin-top:18px;max-width:320px;margin-left:auto">
            <div class="sum-row"><span>Subtotal</span><span><?= money($o['subtotal']) ?></span></div>
            <?php if (!empty($o['coupon_code'])): ?>
              <div class="sum-row" style="color:var(--moss)"><span>Coupon <?= e($o['coupon_code']) ?></span>
                <span><?= (float)($o['discount'] ?? 0) > 0 ? '− ' . money($o['discount']) : 'Free shipping' ?></span></div>
            <?php endif; ?>
            <div class="sum-row"><span>Shipping</span><span><?= $o['shipping'] > 0 ? money($o['shipping']) : 'Free' ?></span></div>
            <div class="sum-row"><span>Tax (<?= TAX_PERCENT ?>%)</span><span><?= money($o['tax']) ?></span></div>
            <div class="sum-row total"><span>Total</span><span><?= money($o['total']) ?></span></div>
          </div>
        </div>

        <div>
          <div class="adm-card">
            <h3>Update status</h3>
            <form method="post">
              <?= csrf_field() ?><input type="hidden" name="action" value="order_status"><input type="hidden" name="id" value="<?= (int)$o['id'] ?>">
              <div class="fld" style="margin-bottom:14px"><label>Order status</label>
                <select name="order_status">
                  <?php foreach ($STATUSES as $s): ?>
                    <option <?= $o['order_status'] === $s ? 'selected' : '' ?>><?= e($s) ?></option>
                  <?php endforeach; ?>
                </select></div>
              <div class="fld" style="margin-bottom:18px"><label>Payment status</label>
                <select name="payment_status">
                  <?php foreach ($PAYSTAT as $s): ?>
                    <option <?= $o['payment_status'] === $s ? 'selected' : '' ?>><?= e($s) ?></option>
                  <?php endforeach; ?>
                </select></div>
              <button class="btn sm wide"><span>Save status</span></button>
            </form>
          </div>
          <div class="adm-card">
            <h3>Customer</h3>
            <p><b><?= e($o['customer_name']) ?></b></p>
            <p class="hint"><?= e($o['email']) ?> · <?= e($o['mobile']) ?></p>
            <h4 style="margin-top:16px;font-size:13px;letter-spacing:.1em;text-transform:uppercase;color:var(--mute);font-family:'Jost'">Billing</h4>
            <p style="font-size:14px"><?= e($o['billing_address']) ?><br><?= e($o['city']) ?>, <?= e($o['state']) ?> — <?= e($o['pincode']) ?></p>
            <h4 style="margin-top:14px;font-size:13px;letter-spacing:.1em;text-transform:uppercase;color:var(--mute);font-family:'Jost'">Shipping</h4>
            <p style="font-size:14px"><?= e($o['shipping_address']) ?></p>
            <h4 style="margin-top:14px;font-size:13px;letter-spacing:.1em;text-transform:uppercase;color:var(--mute);font-family:'Jost'">Payment</h4>
            <p style="font-size:14px"><?= e($o['payment_method']) ?> · <span class="<?= status_class($o['payment_status']) ?>"><?= e($o['payment_status']) ?></span></p>
            <?php if (!empty($o['note'])): ?>
              <h4 style="margin-top:14px;font-size:13px;letter-spacing:.1em;text-transform:uppercase;color:var(--mute);font-family:'Jost'">Note</h4>
              <p style="font-size:14px"><?= e($o['note']) ?></p>
            <?php endif; ?>
          </div>
        </div>
      </div>
<?php endif; /* order found */ ?>

<?php
/* ---------------------- ADMIN: COUPONS ---------------------- */
elseif ($page === 'admin_coupons'):
    $edit = null;
    if ($eid = (int)get('edit', 0)) {
        $st = db()->prepare('SELECT * FROM coupons WHERE id=?'); $st->execute([$eid]); $edit = $st->fetch() ?: null;
    }
    $coupons = db()->query('SELECT * FROM coupons ORDER BY id DESC')->fetchAll();
    $redeemed = (float)db()->query('SELECT COALESCE(SUM(amount),0) FROM coupon_uses')->fetchColumn();
    $types = ['percent' => 'Percentage off', 'flat' => 'Flat amount off', 'ship' => 'Free shipping'];
?>
      <div class="adm-card">
        <h3><?= $edit ? 'Edit coupon — ' . e($edit['code']) : 'Create a coupon' ?></h3>
        <form method="post">
          <?= csrf_field() ?>
          <input type="hidden" name="action" value="coupon_save">
          <input type="hidden" name="id" value="<?= (int)($edit['id'] ?? 0) ?>">
          <div class="fgrid">
            <div class="fld"><label>Coupon code</label>
              <input type="text" name="code" required style="text-transform:uppercase" placeholder="SUMMER25"
                     value="<?= e($edit['code'] ?? '') ?>"></div>
            <div class="fld"><label>Discount type</label>
              <select name="type" id="cpType" onchange="cpTypeChange()">
                <?php foreach ($types as $k => $lbl): ?>
                  <option value="<?= $k ?>" <?= ($edit['type'] ?? 'percent') === $k ? 'selected' : '' ?>><?= e($lbl) ?></option>
                <?php endforeach; ?>
              </select></div>
            <div class="fld full"><label>Short description <span class="hint">(shown to shoppers)</span></label>
              <input type="text" name="title" placeholder="Welcome offer — 10% off your first bag" value="<?= e($edit['title'] ?? '') ?>"></div>
            <div class="fld" id="cpValWrap"><label>Value <span class="hint">(% or <?= CURRENCY ?>)</span></label>
              <input type="number" step="0.01" min="0" name="value" value="<?= e($edit['value'] ?? '10') ?>"></div>
            <div class="fld"><label>Minimum order <?= CURRENCY ?></label>
              <input type="number" step="0.01" min="0" name="min_order" value="<?= e($edit['min_order'] ?? '0') ?>"></div>
            <div class="fld" id="cpCapWrap"><label>Maximum discount <?= CURRENCY ?> <span class="hint">(0 = no cap)</span></label>
              <input type="number" step="0.01" min="0" name="max_discount" value="<?= e($edit['max_discount'] ?? '0') ?>"></div>
            <div class="fld"><label>Total uses allowed <span class="hint">(0 = unlimited)</span></label>
              <input type="number" min="0" name="usage_limit" value="<?= e($edit['usage_limit'] ?? '0') ?>"></div>
            <div class="fld"><label>Uses per customer <span class="hint">(0 = unlimited)</span></label>
              <input type="number" min="0" name="per_user_limit" value="<?= e($edit['per_user_limit'] ?? '0') ?>"></div>
            <div class="fld"><label>Expires on <span class="hint">(optional)</span></label>
              <input type="date" name="expires_at" value="<?= e($edit['expires_at'] ?? '') ?>"></div>
            <div class="fld"><label>Show on cart page</label>
              <select name="is_public">
                <option value="1" <?= (int)($edit['is_public'] ?? 1) === 1 ? 'selected' : '' ?>>Yes — list it publicly</option>
                <option value="0" <?= isset($edit) && (int)$edit['is_public'] === 0 ? 'selected' : '' ?>>No — secret code</option>
              </select></div>
            <div class="fld"><label>Status</label>
              <select name="status">
                <option value="1" <?= (int)($edit['status'] ?? 1) === 1 ? 'selected' : '' ?>>Active</option>
                <option value="0" <?= isset($edit) && (int)$edit['status'] === 0 ? 'selected' : '' ?>>Disabled</option>
              </select></div>
          </div>
          <div style="margin-top:18px;display:flex;gap:10px">
            <button class="btn sm"><span><?= $edit ? 'Update coupon' : 'Create coupon' ?></span></button>
            <?php if ($edit): ?><a class="btn sm ghost" href="<?= url(['page' => 'admin_coupons']) ?>"><span>Cancel</span></a><?php endif; ?>
          </div>
        </form>
      </div>

      <div class="adm-card">
        <h3>All coupons (<?= count($coupons) ?>) <span class="hint" style="margin-left:10px"><?= money($redeemed) ?> given away so far</span></h3>
        <div style="overflow-x:auto">
        <table class="tbl">
          <thead><tr><th>Code</th><th>Offer</th><th>Min order</th><th>Used</th><th>Expires</th><th>Status</th><th>Actions</th></tr></thead>
          <tbody>
          <?php if (!$coupons): ?>
            <tr><td colspan="7" class="hint">No coupons yet — create one above.</td></tr>
          <?php else: foreach ($coupons as $cp):
                $expired = !empty($cp['expires_at']) && $cp['expires_at'] < date('Y-m-d'); ?>
            <tr>
              <td><b style="letter-spacing:.12em"><?= e($cp['code']) ?></b>
                  <div class="hint"><?= e(mb_substr((string)$cp['title'], 0, 40)) ?></div></td>
              <td><?= e(coupon_label($cp)) ?><?= (int)$cp['is_public'] === 0 ? '<div class="hint">Secret</div>' : '' ?></td>
              <td><?= (float)$cp['min_order'] > 0 ? money($cp['min_order']) : '—' ?></td>
              <td><?= (int)$cp['used_count'] ?><?= (int)$cp['usage_limit'] > 0 ? ' / ' . (int)$cp['usage_limit'] : '' ?></td>
              <td><?= !empty($cp['expires_at']) ? date('d M Y', strtotime($cp['expires_at'])) : 'Never' ?></td>
              <td><?php if ((int)$cp['status'] !== 1): ?><span class="stx cancelled">Disabled</span>
                  <?php elseif ($expired): ?><span class="stx pending">Expired</span>
                  <?php else: ?><span class="stx delivered">Active</span><?php endif; ?></td>
              <td><div class="row-acts">
                <a class="mini" href="<?= url(['page' => 'admin_coupons', 'edit' => $cp['id']]) ?>">Edit</a>
                <form method="post" onsubmit="return confirm('Delete coupon <?= e($cp['code']) ?>?')">
                  <?= csrf_field() ?><input type="hidden" name="action" value="coupon_delete"><input type="hidden" name="id" value="<?= (int)$cp['id'] ?>">
                  <button class="mini del">Delete</button>
                </form>
              </div></td>
            </tr>
          <?php endforeach; endif; ?>
          </tbody>
        </table>
        </div>
      </div>
      <script>
      function cpTypeChange(){
        var t=document.getElementById('cpType').value;
        document.getElementById('cpValWrap').style.display = (t==='ship')?'none':'flex';
        document.getElementById('cpCapWrap').style.display = (t==='percent')?'flex':'none';
      }
      cpTypeChange();
      </script>

<?php
/* ---------------------- ADMIN: MESSAGES ---------------------- */
elseif ($page === 'admin_messages'):
    $msgs = db()->query('SELECT * FROM contacts ORDER BY id DESC')->fetchAll();
?>
      <div class="adm-card">
        <h3>Contact form messages (<?= count($msgs) ?>)</h3>
        <?php if (!$msgs): ?><p class="hint">No messages yet.</p><?php endif; ?>
        <?php foreach ($msgs as $m): ?>
          <div style="border:1px solid var(--line);border-radius:var(--r-m);padding:18px;margin-bottom:12px;background:<?= (int)$m['is_read'] ? '#fff' : '#FFFBF6' ?>">
            <div style="display:flex;gap:12px;align-items:center;flex-wrap:wrap">
              <b><?= e($m['name']) ?></b>
              <span class="hint"><?= e($m['email']) ?></span>
              <?php if (!(int)$m['is_read']): ?><span class="stx pending">New</span><?php endif; ?>
              <span class="hint" style="margin-left:auto"><?= date('d M Y, H:i', strtotime($m['created_at'])) ?></span>
            </div>
            <?php if ($m['subject']): ?><p style="margin-top:8px;color:var(--ink)"><b><?= e($m['subject']) ?></b></p><?php endif; ?>
            <p style="margin-top:6px;font-size:14px"><?= nl2br(e($m['message'])) ?></p>
            <?php if (!(int)$m['is_read']): ?>
              <form method="post" style="margin-top:10px">
                <?= csrf_field() ?><input type="hidden" name="action" value="contact_read"><input type="hidden" name="id" value="<?= (int)$m['id'] ?>">
                <button class="mini">Mark as read</button>
              </form>
            <?php endif; ?>
          </div>
        <?php endforeach; ?>
      </div>
<?php endif; ?>

    </div><!-- /adm-body -->
  </div><!-- /adm-main -->
</div><!-- /adm -->
<script>
document.getElementById('sideToggle')?.addEventListener('click',()=>document.getElementById('side').classList.toggle('open'));
setTimeout(()=>document.querySelectorAll('.toast').forEach(t=>{t.classList.add('out');setTimeout(()=>t.remove(),350)}),3600);
</script>
</body></html>
<?php
    exit;
endif;

/* ======================================================================
   STOREFRONT
   ====================================================================== */
$user     = current_user();
$navCats  = all_categories();
$wish     = wishlist();
?>

<div class="announce">
  <span>Free shipping on orders above <b><?= CURRENCY . FREE_SHIP_ABOVE ?></b> &nbsp;·&nbsp; Dermatologically tested &nbsp;·&nbsp; 100% cruelty-free<br>
        New here? Use code <b>LUMEA10</b> for 10% off your first order<br>
        Easy 14-day returns &nbsp;·&nbsp; Ships across India in 3–5 days</span>
</div>

<header class="site" id="siteHead">
  <div class="wrap nav">
    <a href="<?= url(['page' => 'home']) ?>" class="logo"><?= e(SITE_NAME) ?><small><?= e(SITE_TAG) ?></small></a>
    <nav class="menu" id="menu">
      <a href="<?= url(['page' => 'home']) ?>"       class="<?= $page === 'home' ? 'on' : '' ?>">Home</a>
      <a href="<?= url(['page' => 'categories']) ?>" class="<?= $page === 'categories' ? 'on' : '' ?>">Categories</a>
      <a href="<?= url(['page' => 'shop']) ?>"       class="<?= in_array($page, ['shop', 'product'], true) ? 'on' : '' ?>">Shop</a>
      <a href="<?= url(['page' => 'about']) ?>"      class="<?= $page === 'about' ? 'on' : '' ?>">About</a>
      <a href="<?= url(['page' => 'contact']) ?>"    class="<?= $page === 'contact' ? 'on' : '' ?>">Contact</a>
    </nav>
    <div class="nav-tools">
      <form class="searchbar" method="get">
        <input type="hidden" name="page" value="shop">
        <input type="text" name="q" placeholder="Search products…" value="<?= e((string)get('q', '')) ?>">
        <button type="submit"><svg viewBox="0 0 24 24" width="15" height="15" style="stroke:#fff;fill:none;stroke-width:2"><circle cx="11" cy="11" r="7"/><path d="M21 21l-4.3-4.3"/></svg></button>
      </form>
      <a class="ico" href="<?= url(['page' => 'wishlist']) ?>" title="Wishlist">
        <svg viewBox="0 0 24 24"><path d="M20.8 5.6a5.5 5.5 0 00-7.8 0L12 6.6l-1-1a5.5 5.5 0 10-7.8 7.8l8.8 8.8 8.8-8.8a5.5 5.5 0 000-7.8z"/></svg>
        <?php if ($wish): ?><span class="badge" id="wishBadge"><?= count($wish) ?></span><?php else: ?><span class="badge" id="wishBadge" style="display:none">0</span><?php endif; ?>
      </a>
      <a class="ico" href="<?= url(['page' => $user ? 'profile' : 'login']) ?>" title="<?= $user ? 'My account' : 'Log in' ?>">
        <svg viewBox="0 0 24 24"><circle cx="12" cy="8" r="4"/><path d="M4 21c0-4 3.6-7 8-7s8 3 8 7"/></svg>
      </a>
      <a class="ico" href="<?= url(['page' => 'cart']) ?>" title="Shopping bag">
        <svg viewBox="0 0 24 24"><path d="M6 2l1.5 3h9L18 2"/><rect x="3" y="6" width="18" height="15" rx="2"/><path d="M8 10a4 4 0 008 0"/></svg>
        <span class="badge" id="cartBadge" <?= cart_count() ? '' : 'style="display:none"' ?>><?= cart_count() ?></span>
      </a>
      <button class="ico burger" id="burger"><svg viewBox="0 0 24 24"><path d="M3 6h18M3 12h18M3 18h18"/></svg></button>
    </div>
  </div>
</header>

<main>
<?php
/* ======================= PAGE: HOME ======================= */
if ($page === 'home'):
    $featured = products_query(['featured' => 1, 'limit' => 8]);
    $latest   = products_query(['limit' => 4]);
    $cats     = all_categories();
    $slides = [
        ['Skin first, always', 'The barrier-repair edit', 'Ceramides, niacinamide and hyaluronic acid in formulas that respect your skin barrier instead of stripping it.', 'shop'],
        ['New season', 'Colour that behaves like skin', 'Buildable pigments, breathable textures and shades made for warm Indian undertones.', 'shop'],
        ['Festive offer', 'Up to 25% off sitewide', 'Our best-loved serums, masks and eau de parfums — discounted until the end of the month.', 'shop'],
    ];
?>
  <section class="hero" id="hero">
    <?php foreach ($slides as $i => $s): ?>
      <div class="slide <?= $i === 0 ? 'on' : '' ?>">
        <div class="bgimg" style="background-image:url('<?= hero_photo($i) ?>')"></div>
        <div class="slide-in">
          <div class="eyebrow"><?= e($s[0]) ?></div>
          <h1><?= e($s[1]) ?></h1>
          <p><?= e($s[2]) ?></p>
          <div class="btns">
            <a class="btn" href="<?= url(['page' => $s[3]]) ?>"><span>Shop the edit</span></a>
            <a class="btn ghost" style="color:#fff;border-color:rgba(255,255,255,.45)" href="<?= url(['page' => 'categories']) ?>"><span>Browse categories</span></a>
          </div>
        </div>
      </div>
    <?php endforeach; ?>
    <button class="hero-arrow prev" id="hPrev"><svg viewBox="0 0 24 24" width="18" height="18" style="stroke:currentColor;fill:none;stroke-width:1.8"><path d="M15 18l-6-6 6-6"/></svg></button>
    <button class="hero-arrow next" id="hNext"><svg viewBox="0 0 24 24" width="18" height="18" style="stroke:currentColor;fill:none;stroke-width:1.8"><path d="M9 6l6 6-6 6"/></svg></button>
    <div class="hero-dots" id="hDots">
      <?php foreach ($slides as $i => $s): ?><button class="<?= $i === 0 ? 'on' : '' ?>" data-i="<?= $i ?>"></button><?php endforeach; ?>
    </div>
  </section>

  <div class="trust">
    <div class="trust-track">
      <?php for ($k = 0; $k < 2; $k++): ?>
        <span><i>✦</i> Dermatologically tested</span><span><i>✦</i> Cruelty-free &amp; vegan</span>
        <span><i>✦</i> No parabens or sulphates</span><span><i>✦</i> Made in India</span>
        <span><i>✦</i> Free shipping above <?= CURRENCY . FREE_SHIP_ABOVE ?></span><span><i>✦</i> 14-day easy returns</span>
      <?php endfor; ?>
    </div>
  </div>

  <section class="sec">
    <div class="wrap">
      <div class="sec-head rv">
        <div>
          <div class="eyebrow">Shop by concern</div>
          <h2>Featured categories</h2>
        </div>
        <a class="link-u" href="<?= url(['page' => 'categories']) ?>">View all categories →</a>
      </div>
      <div class="cat-grid">
        <?php foreach ($cats as $c): ?>
          <a class="cat-card rv" href="<?= url(['page' => 'shop', 'cat' => $c['id']]) ?>">
            <img src="<?= img_src($c['image'], $c['name'] ?? '', $c['slug'] ?? '') ?>" alt="<?= e($c['name']) ?>" loading="lazy" onerror="this.onerror=null;this.src='<?= e(art_fallback($c['name'] ?? '', $c['slug'] ?? '')) ?>';">
            <div class="cat-body">
              <h3><?= e($c['name']) ?></h3>
              <p><?= (int)$c['product_count'] ?> products</p>
              <span class="go">Explore →</span>
            </div>
          </a>
        <?php endforeach; ?>
      </div>
    </div>
  </section>

  <section class="sec" style="padding-top:0">
    <div class="wrap">
      <div class="sec-head rv">
        <div>
          <div class="eyebrow">Loved by our customers</div>
          <h2>Featured products</h2>
        </div>
        <a class="link-u" href="<?= url(['page' => 'shop']) ?>">Shop everything →</a>
      </div>
      <div class="p-grid">
        <?php foreach ($featured as $p) product_card($p, $wish); ?>
      </div>
    </div>
  </section>

  <section class="sec" style="padding-top:0">
    <div class="wrap">
      <div class="offer rv">
        <div>
          <div class="eyebrow" style="color:#E7C0AC">Limited time</div>
          <h2>Festive edit — up to 25% off</h2>
          <p>Our bestselling serums, bond-repair masks and eau de parfums at their lowest price of the year. Offer ends when the clock runs out.</p>
          <a class="btn" href="<?= url(['page' => 'shop', 'sort' => 'price_low']) ?>"><span>Shop the offer</span></a>
        </div>
        <div class="right">
          <div class="countdown" id="cd">
            <div class="cd-box"><b id="cdD">00</b><span>Days</span></div>
            <div class="cd-box"><b id="cdH">00</b><span>Hours</span></div>
            <div class="cd-box"><b id="cdM">00</b><span>Mins</span></div>
            <div class="cd-box"><b id="cdS">00</b><span>Secs</span></div>
          </div>
        </div>
      </div>
    </div>
  </section>

  <section class="sec" style="padding-top:0">
    <div class="wrap">
      <div class="sec-head rv">
        <div>
          <div class="eyebrow">Fresh on the shelf</div>
          <h2>New arrivals</h2>
        </div>
        <a class="link-u" href="<?= url(['page' => 'shop', 'sort' => 'newest']) ?>">See what's new →</a>
      </div>
      <div class="p-grid">
        <?php foreach ($latest as $p) product_card($p, $wish); ?>
      </div>
    </div>
  </section>

  <section class="sec" style="padding-top:0">
    <div class="wrap">
      <div class="sec-head rv"><div><div class="eyebrow">Real reviews</div><h2>What people say</h2></div></div>
      <div class="quote-grid">
        <div class="quote rv"><div class="qm">&ldquo;</div><p>The niacinamide serum is the only thing that has calmed my post-acne marks. Four weeks in and my skin finally looks even.</p><div class="who">Ananya R. · Ahmedabad</div></div>
        <div class="quote rv"><div class="qm">&ldquo;</div><p>Skin tint with SPF 30 means one less step in the morning. Coverage is light but it genuinely evens everything out.</p><div class="who">Meera J. · Pune</div></div>
        <div class="quote rv"><div class="qm">&ldquo;</div><p>Amber Dusk lasts a full working day on me. It smells expensive without being loud, which is exactly what I wanted.</p><div class="who">Karan S. · Bengaluru</div></div>
      </div>
    </div>
  </section>

  <section class="sec" style="padding-top:0">
    <div class="wrap">
      <div class="news rv">
        <div class="eyebrow">Stay in the loop</div>
        <h2 style="margin-top:8px">Get 10% off your first order</h2>
        <p class="lede" style="margin:10px auto 0">Skincare notes, restock alerts and early access to every sale. No spam — we promise.</p>
        <form onsubmit="event.preventDefault();toast('Thanks for subscribing! Check your inbox.');this.reset();">
          <input type="email" placeholder="your@email.com" required>
          <button class="btn"><span>Subscribe</span></button>
        </form>
      </div>
    </div>
  </section>

<?php
/* ======================= PAGE: CATEGORIES ======================= */
elseif ($page === 'categories'):
    $cats = all_categories();
?>
  <div class="phead"><div class="wrap">
    <div class="crumb"><a href="<?= url(['page' => 'home']) ?>">Home</a> / Categories</div>
    <h1 style="font-size:clamp(32px,5vw,52px)">Shop by category</h1>
    <p class="lede">Four edits, each formulated around one job — protect the barrier, add colour, care for the scalp, or wear a scent you actually like.</p>
  </div></div>
  <section class="sec"><div class="wrap">
    <div class="cat-grid">
      <?php foreach ($cats as $c): ?>
        <a class="cat-card rv" href="<?= url(['page' => 'shop', 'cat' => $c['id']]) ?>" style="aspect-ratio:4/5">
          <img src="<?= img_src($c['image'], $c['name'] ?? '', $c['slug'] ?? '') ?>" alt="<?= e($c['name']) ?>" loading="lazy" onerror="this.onerror=null;this.src='<?= e(art_fallback($c['name'] ?? '', $c['slug'] ?? '')) ?>';">
          <div class="cat-body">
            <h3><?= e($c['name']) ?></h3>
            <p><?= e(mb_substr((string)$c['description'], 0, 72)) ?>…</p>
            <span class="go"><?= (int)$c['product_count'] ?> products · View →</span>
          </div>
        </a>
      <?php endforeach; ?>
    </div>
  </div></section>

<?php
/* ======================= PAGE: SHOP / PRODUCT LISTING ======================= */
elseif ($page === 'shop'):
    $catId = (int)get('cat', 0);
    $sort  = (string)get('sort', 'newest');
    $q     = trim((string)get('q', ''));
    $list  = products_query(['category' => $catId, 'sort' => $sort, 'search' => $q]);
    $cats  = all_categories();
    $curCat = null;
    foreach ($cats as $c) if ((int)$c['id'] === $catId) $curCat = $c;
?>
  <div class="phead"><div class="wrap">
    <div class="crumb"><a href="<?= url(['page' => 'home']) ?>">Home</a> / <a href="<?= url(['page' => 'shop']) ?>">Shop</a><?= $curCat ? ' / ' . e($curCat['name']) : '' ?></div>
    <h1 style="font-size:clamp(32px,5vw,52px)"><?= $curCat ? e($curCat['name']) : ($q !== '' ? 'Search: “' . e($q) . '”' : 'All products') ?></h1>
    <p class="lede"><?= $curCat ? e($curCat['description']) : 'Everything we make, in one place — filter by category, price or rating.' ?></p>
  </div></div>

  <section class="sec"><div class="wrap">
    <div class="chipbar">
      <a class="chip <?= $catId === 0 ? 'on' : '' ?>" href="<?= url(['page' => 'shop', 'sort' => $sort]) ?>">All</a>
      <?php foreach ($cats as $c): ?>
        <a class="chip <?= $catId === (int)$c['id'] ? 'on' : '' ?>" href="<?= url(['page' => 'shop', 'cat' => $c['id'], 'sort' => $sort]) ?>"><?= e($c['name']) ?></a>
      <?php endforeach; ?>
    </div>

    <form class="filters" method="get">
      <input type="hidden" name="page" value="shop">
      <input type="text" name="q" placeholder="Search products…" value="<?= e($q) ?>">
      <select name="cat">
        <option value="0">All categories</option>
        <?php foreach ($cats as $c): ?>
          <option value="<?= (int)$c['id'] ?>" <?= $catId === (int)$c['id'] ? 'selected' : '' ?>><?= e($c['name']) ?></option>
        <?php endforeach; ?>
      </select>
      <select name="sort">
        <option value="newest"     <?= $sort === 'newest' ? 'selected' : '' ?>>Sort: Newest first</option>
        <option value="price_low"  <?= $sort === 'price_low' ? 'selected' : '' ?>>Sort: Price low → high</option>
        <option value="price_high" <?= $sort === 'price_high' ? 'selected' : '' ?>>Sort: Price high → low</option>
        <option value="rating"     <?= $sort === 'rating' ? 'selected' : '' ?>>Sort: Top rated</option>
        <option value="name"       <?= $sort === 'name' ? 'selected' : '' ?>>Sort: A → Z</option>
      </select>
      <button class="btn sm"><span>Apply</span></button>
      <a class="btn sm ghost" href="<?= url(['page' => 'shop']) ?>"><span>Reset</span></a>
      <span class="count"><?= count($list) ?> product<?= count($list) === 1 ? '' : 's' ?></span>
    </form>

    <?php if (!$list): ?>
      <div class="empty">
        <svg viewBox="0 0 24 24"><circle cx="11" cy="11" r="7"/><path d="M21 21l-4.3-4.3"/></svg>
        <h3>Nothing matched that search</h3>
        <p class="lede center" style="margin:6px auto 18px">Try a different keyword, or browse the full catalogue.</p>
        <a class="btn" href="<?= url(['page' => 'shop']) ?>"><span>View all products</span></a>
      </div>
    <?php else: ?>
      <div class="p-grid"><?php foreach ($list as $p) product_card($p, $wish); ?></div>
    <?php endif; ?>
  </div></section>

<?php
/* ======================= PAGE: PRODUCT DETAIL ======================= */
elseif ($page === 'product'):
    $p = find_product((int)get('id', 0));
    if (!$p || (int)$p['status'] !== 1):
        echo '<div class="empty wrap"><h2>Product not found</h2><p class="lede center" style="margin:8px auto 20px">It may have been removed from the catalogue.</p><a class="btn" href="' . url(['page' => 'shop']) . '"><span>Back to shop</span></a></div>';
    else:
        $rel = products_query(['category' => (int)$p['category_id'], 'limit' => 4]);
        $rel = array_values(array_filter($rel, fn($r) => (int)$r['id'] !== (int)$p['id']));
        $specs = array_filter(array_map('trim', explode('|', (string)$p['specs'])));
?>
  <div class="phead" style="padding:28px 0"><div class="wrap">
    <div class="crumb" style="margin:0">
      <a href="<?= url(['page' => 'home']) ?>">Home</a> /
      <a href="<?= url(['page' => 'shop']) ?>">Shop</a> /
      <a href="<?= url(['page' => 'shop', 'cat' => $p['category_id']]) ?>"><?= e($p['category_name']) ?></a> /
      <?= e($p['name']) ?>
    </div>
  </div></div>

  <section class="sec" style="padding-top:44px"><div class="wrap">
    <div class="pd">
      <div class="pd-media rv">
        <?php if (discount_percent($p)): ?><span class="tag" style="position:absolute;top:16px;left:16px;z-index:2"><?= discount_percent($p) ?>% off</span><?php endif; ?>
        <img src="<?= img_src($p['image'], $p['name'] ?? '', $p['category_slug'] ?? '') ?>" alt="<?= e($p['name']) ?>" onerror="this.onerror=null;this.src='<?= e(art_fallback($p['name'] ?? '', $p['category_slug'] ?? '')) ?>';">
      </div>

      <div class="rv">
        <div class="eyebrow"><?= e($p['category_name']) ?></div>
        <h1><?= e($p['name']) ?></h1>
        <div class="rate-row"><?= star_html((float)$p['rating']) ?> <span><?= number_format((float)$p['rating'], 1) ?> · verified reviews</span></div>

        <div class="pd-price">
          <span class="now"><?= money(final_price($p)) ?></span>
          <?php if (discount_percent($p)): ?>
            <span class="was"><?= money($p['price']) ?></span>
            <span class="pill">You save <?= money((float)$p['price'] - final_price($p)) ?></span>
          <?php endif; ?>
        </div>

        <p style="color:var(--ink-2)"><?= e($p['short_desc']) ?></p>

        <div style="display:flex;gap:14px;align-items:center;margin:22px 0;flex-wrap:wrap">
          <?php if ((int)$p['stock'] > 0): ?>
            <span class="stx delivered">In stock — <?= (int)$p['stock'] ?> available</span>
          <?php else: ?>
            <span class="stx cancelled">Out of stock</span>
          <?php endif; ?>
          <span class="hint">Ships in 3–5 days</span>
        </div>

        <form method="post" style="display:flex;gap:12px;flex-wrap:wrap;align-items:center">
          <?= csrf_field() ?>
          <input type="hidden" name="action" value="cart_add">
          <input type="hidden" name="product_id" value="<?= (int)$p['id'] ?>">
          <div class="qty">
            <button type="button" onclick="stepQty(this,-1)">−</button>
            <input type="number" name="qty" value="1" min="1" max="<?= max(1, (int)$p['stock']) ?>"
                   data-stock="<?= (int)$p['stock'] ?>" data-name="<?= e($p['name']) ?>" readonly>
            <button type="button" onclick="stepQty(this,1)">+</button>
          </div>
          <button class="btn" <?= (int)$p['stock'] < 1 ? 'disabled' : '' ?>><span>Add to bag</span></button>
          <button class="btn dark" name="buynow" value="1" formaction="<?= url(['page' => 'cart']) ?>" <?= (int)$p['stock'] < 1 ? 'disabled' : '' ?>><span>Buy now</span></button>
          <button type="button" class="ico" style="border:1px solid var(--line)" onclick="toggleWish(<?= (int)$p['id'] ?>,this)">
            <svg viewBox="0 0 24 24"><path d="M20.8 5.6a5.5 5.5 0 00-7.8 0L12 6.6l-1-1a5.5 5.5 0 10-7.8 7.8l8.8 8.8 8.8-8.8a5.5 5.5 0 000-7.8z"/></svg>
          </button>
        </form>

        <div class="tabs-h">
          <button class="on" data-tab="desc">Description</button>
          <button data-tab="spec">Specifications</button>
          <button data-tab="ship">Shipping &amp; returns</button>
        </div>
        <div class="tabpane on" id="tab-desc"><p><?= nl2br(e($p['description'])) ?></p></div>
        <div class="tabpane" id="tab-spec">
          <?php if ($specs): ?>
            <ul class="spec-list">
              <?php foreach ($specs as $s):
                $parts = explode(':', $s, 2); ?>
                <li><b><?= e(trim($parts[0])) ?></b><span><?= e(trim($parts[1] ?? '—')) ?></span></li>
              <?php endforeach; ?>
            </ul>
          <?php else: ?><p class="hint">No specifications listed for this product.</p><?php endif; ?>
        </div>
        <div class="tabpane" id="tab-ship">
          <ul class="spec-list">
            <li><b>Delivery</b><span>3–5 working days across India</span></li>
            <li><b>Shipping</b><span>Free above <?= CURRENCY . FREE_SHIP_ABOVE ?>, otherwise <?= money(SHIP_FEE) ?></span></li>
            <li><b>Returns</b><span>14 days from delivery, unopened products</span></li>
            <li><b>Payment</b><span>Cash on delivery, UPI, cards and net banking</span></li>
          </ul>
        </div>
      </div>
    </div>

    <?php if ($rel): ?>
      <div class="sec-head rv" style="margin-top:70px"><div><div class="eyebrow">You may also like</div><h2>More from <?= e($p['category_name']) ?></h2></div></div>
      <div class="p-grid"><?php foreach ($rel as $r) product_card($r, $wish); ?></div>
    <?php endif; ?>
  </div></section>
<?php endif; ?>

<?php
/* ======================= PAGE: CART ======================= */
elseif ($page === 'cart'):
    $items  = cart_detailed();
    $t      = cart_totals();
    $prog   = FREE_SHIP_ABOVE > 0 ? min(100, ($t['subtotal'] / FREE_SHIP_ABOVE) * 100) : 100;
    $issues = cart_stock_issues();
    $offers = public_coupons();
    $uidNow = isset($_SESSION['uid']) ? (int)$_SESSION['uid'] : null;
?>
  <div class="phead"><div class="wrap">
    <div class="crumb"><a href="<?= url(['page' => 'home']) ?>">Home</a> / Shopping bag</div>
    <h1 style="font-size:clamp(32px,5vw,52px)">Your bag</h1>
  </div></div>
  <section class="sec"><div class="wrap">
    <?php if (!$items): ?>
      <div class="empty">
        <svg viewBox="0 0 24 24"><path d="M6 2l1.5 3h9L18 2"/><rect x="3" y="6" width="18" height="15" rx="2"/><path d="M8 10a4 4 0 008 0"/></svg>
        <h2>Your bag is empty</h2>
        <p class="lede center" style="margin:8px auto 22px">Add a few favourites and they'll show up right here.</p>
        <a class="btn" href="<?= url(['page' => 'shop']) ?>"><span>Start shopping</span></a>
      </div>
    <?php else: ?>
      <?php if ($issues): ?>
        <div class="alert error" style="margin-bottom:18px">
          <b>Your order cannot be placed yet.</b><br>
          <?php foreach ($issues as $b): ?>• <?= e($b) ?><br><?php endforeach; ?>
        </div>
      <?php endif; ?>
      <div class="two-col">
        <div class="panel">
          <form method="post" id="cartForm">
            <?= csrf_field() ?><input type="hidden" name="action" value="cart_update">
            <?php foreach ($items as $it): ?>
              <div class="cart-row">
                <img src="<?= img_src($it['image'], $it['name'] ?? '', '') ?>" alt="<?= e($it['name']) ?>" onerror="this.onerror=null;this.src='<?= e(art_fallback($it['name'] ?? '', '')) ?>';">
                <div>
                  <div class="p-cat"><?= e($it['category_name'] ?? '') ?></div>
                  <h3 style="font-size:20px;margin:2px 0 4px"><a href="<?= url(['page' => 'product', 'id' => $it['id']]) ?>"><?= e($it['name']) ?></a></h3>
                  <p class="hint"><?= money($it['unit_price']) ?> each<?= discount_percent($it) ? ' · ' . discount_percent($it) . '% off' : '' ?></p>
                  <div style="display:flex;gap:12px;align-items:center;margin-top:10px;flex-wrap:wrap">
                    <div class="qty">
                      <button type="button" onclick="stepQty(this,-1,true)">−</button>
                      <input type="number" name="qty[<?= (int)$it['id'] ?>]" value="<?= (int)$it['qty'] ?>"
                             min="0" max="<?= max(0, (int)$it['stock_left']) ?>" data-stock="<?= (int)$it['stock_left'] ?>"
                             data-name="<?= e($it['name']) ?>" readonly>
                      <button type="button" onclick="stepQty(this,1,true)">+</button>
                    </div>
                    <button type="button" class="mini del" onclick="removeItem(<?= (int)$it['id'] ?>)">Remove</button>
                  </div>
                  <?php if ($it['over']): ?>
                    <div class="stock-warn">
                      <b>!</b>
                      <span><?php if ((int)$it['stock_left'] < 1): ?>
                        Out of stock — this item has to be removed before you can place the order.
                      <?php else: ?>
                        Only <?= (int)$it['stock_left'] ?> <?= (int)$it['stock_left'] === 1 ? 'piece is' : 'pieces are' ?> available.
                        Reduce the quantity to <?= (int)$it['stock_left'] ?> or remove the item — the order cannot be placed as it is.
                      <?php endif; ?></span>
                    </div>
                  <?php elseif ((int)$it['stock_left'] <= 5): ?>
                    <p class="stock-low">Hurry — only <?= (int)$it['stock_left'] ?> left in stock.</p>
                  <?php endif; ?>
                </div>
                <div style="text-align:right">
                  <b style="font-family:'Cormorant Garamond',serif;font-size:23px;color:var(--ink)"><?= money($it['line_total']) ?></b>
                </div>
              </div>
            <?php endforeach; ?>
            <div style="display:flex;gap:10px;margin-top:20px;flex-wrap:wrap">
              <button class="btn sm ghost"><span>Update bag</span></button>
              <a class="btn sm ghost" href="<?= url(['page' => 'shop']) ?>"><span>Continue shopping</span></a>
            </div>
          </form>
          <form method="post" id="rmForm" style="display:none">
            <?= csrf_field() ?><input type="hidden" name="action" value="cart_remove"><input type="hidden" name="product_id" id="rmId">
          </form>
        </div>

        <div class="panel" style="position:sticky;top:110px">
          <h3 style="margin-bottom:14px">Order summary</h3>
          <?php if ($t['shipping'] > 0): ?>
            <p class="hint">Add <b><?= money(FREE_SHIP_ABOVE - $t['subtotal']) ?></b> more for free shipping</p>
          <?php else: ?>
            <p class="hint" style="color:var(--moss)">✓ You've unlocked free shipping</p>
          <?php endif; ?>
          <div class="ship-bar"><i style="width:<?= $prog ?>%"></i></div>

          <!-- ===================== COUPON ===================== -->
          <div class="cpn-box">
            <?php if ($t['coupon'] !== ''): ?>
              <div class="cpn-on">
                <div style="display:flex;gap:10px;align-items:flex-start">
                  <span class="tick-s">✓</span>
                  <div>
                    <span class="cpn-code"><?= e($t['coupon']) ?></span>
                    <div class="cpn-saved">You saved <?= money($t['saved']) ?> on this order.</div>
                  </div>
                </div>
                <form method="post">
                  <?= csrf_field() ?><input type="hidden" name="action" value="coupon_remove">
                  <button class="mini del">Remove</button>
                </form>
              </div>
            <?php else: ?>
              <form method="post" class="cpn-form">
                <?= csrf_field() ?><input type="hidden" name="action" value="coupon_apply">
                <input type="text" name="code" placeholder="COUPON CODE" autocomplete="off" maxlength="40">
                <button class="btn sm"><span>Apply</span></button>
              </form>
              <?php if ($t['coupon_note'] !== ''): ?>
                <p class="hint" style="color:var(--danger);margin-top:9px"><?= e($t['coupon_note']) ?></p>
              <?php endif; ?>
            <?php endif; ?>

            <?php if ($offers): ?>
              <button type="button" class="cpn-toggle" onclick="toggleOffers(this)">View available coupons (<?= count($offers) ?>)</button>
              <div class="cpn-list" id="cpnList" style="display:none">
                <?php foreach ($offers as $cp):
                      $why = coupon_problem($cp, (float)$t['subtotal'], $uidNow); ?>
                  <div class="cpn-item">
                    <div style="min-width:0">
                      <span class="cd"><?= e($cp['code']) ?></span>
                      <small><?= e(coupon_label($cp)) ?><?= (float)$cp['min_order'] > 0 ? ' · min ' . money($cp['min_order']) : '' ?></small>
                      <?php if ($why !== ''): ?><small style="color:var(--danger)"><?= e($why) ?></small><?php endif; ?>
                    </div>
                    <?php if ($why === '' && $t['coupon'] !== $cp['code']): ?>
                      <form method="post">
                        <?= csrf_field() ?><input type="hidden" name="action" value="coupon_apply">
                        <input type="hidden" name="code" value="<?= e($cp['code']) ?>">
                        <button class="mini">Apply</button>
                      </form>
                    <?php elseif ($t['coupon'] === $cp['code']): ?>
                      <span class="stx delivered">Applied</span>
                    <?php endif; ?>
                  </div>
                <?php endforeach; ?>
              </div>
            <?php endif; ?>
          </div>

          <div class="sum-row"><span>Subtotal</span><span><?= money($t['subtotal']) ?></span></div>
          <?php if ($t['discount'] > 0): ?>
            <div class="sum-row" style="color:var(--moss)"><span>Coupon discount (<?= e($t['coupon']) ?>)</span><span>− <?= money($t['discount']) ?></span></div>
          <?php endif; ?>
          <div class="sum-row"><span>Shipping</span>
            <span><?php if ($t['ship_saved'] > 0): ?><span style="color:var(--moss)">Free (<?= e($t['coupon']) ?>)</span><?php else: ?><?= $t['shipping'] > 0 ? money($t['shipping']) : 'Free' ?><?php endif; ?></span></div>
          <div class="sum-row"><span>Tax (<?= TAX_PERCENT ?>% GST)</span><span><?= money($t['tax']) ?></span></div>
          <div class="sum-row total"><span>Total</span><span><?= money($t['total']) ?></span></div>
          <?php if ($issues): ?>
            <button class="btn wide" style="margin-top:18px" disabled><span>Fix your bag to continue</span></button>
            <p class="hint center" style="margin-top:12px;color:var(--danger)">One or more items exceed the available stock.</p>
          <?php else: ?>
            <a class="btn wide" style="margin-top:18px" href="<?= url(['page' => 'checkout']) ?>"><span>Proceed to checkout</span></a>
            <p class="hint center" style="margin-top:12px">Secure checkout · COD available</p>
          <?php endif; ?>
        </div>
      </div>
    <?php endif; ?>
  </div></section>

<?php
/* ======================= PAGE: WISHLIST ======================= */
elseif ($page === 'wishlist'):
    $items = [];
    if ($wish) {
        $in = implode(',', array_fill(0, count($wish), '?'));
        $st = db()->prepare("SELECT p.*, c.name category_name FROM products p LEFT JOIN categories c ON c.id=p.category_id
                             WHERE p.id IN ($in) AND p.status=1");
        $st->execute(array_map('intval', $wish));
        $items = $st->fetchAll();
    }
?>
  <div class="phead"><div class="wrap">
    <div class="crumb"><a href="<?= url(['page' => 'home']) ?>">Home</a> / Wishlist</div>
    <h1 style="font-size:clamp(32px,5vw,52px)">Saved for later</h1>
  </div></div>
  <section class="sec"><div class="wrap">
    <?php if (!$items): ?>
      <div class="empty">
        <svg viewBox="0 0 24 24"><path d="M20.8 5.6a5.5 5.5 0 00-7.8 0L12 6.6l-1-1a5.5 5.5 0 10-7.8 7.8l8.8 8.8 8.8-8.8a5.5 5.5 0 000-7.8z"/></svg>
        <h2>Your wishlist is empty</h2>
        <p class="lede center" style="margin:8px auto 22px">Tap the heart on any product to save it here.</p>
        <a class="btn" href="<?= url(['page' => 'shop']) ?>"><span>Browse products</span></a>
      </div>
    <?php else: ?>
      <div class="p-grid"><?php foreach ($items as $p) product_card($p, $wish); ?></div>
    <?php endif; ?>
  </div></section>

<?php
/* ======================= PAGE: CHECKOUT ======================= */
elseif ($page === 'checkout'):
    require_login('checkout');
    $items = cart_detailed();
    $t     = cart_totals();
    if (!$items):
        echo '<div class="empty wrap"><h2>Your bag is empty</h2><a class="btn" style="margin-top:18px" href="' . url(['page' => 'shop']) . '"><span>Browse products</span></a></div>';
    else:
?>
  <div class="phead"><div class="wrap">
    <div class="crumb"><a href="<?= url(['page' => 'cart']) ?>">Bag</a> / Checkout</div>
    <h1 style="font-size:clamp(32px,5vw,52px)">Checkout</h1>
  </div></div>
  <section class="sec"><div class="wrap">
    <div class="steps">
      <div class="step done"><div class="bar"></div><div class="dot">✓</div><div class="lb">Bag</div></div>
      <div class="step on"><div class="bar"></div><div class="dot">2</div><div class="lb">Address</div></div>
      <div class="step"><div class="bar"></div><div class="dot">3</div><div class="lb">Payment</div></div>
      <div class="step"><div class="dot">4</div><div class="lb">Done</div></div>
    </div>

    <form method="post"><div class="two-col">
      <div class="panel">
        <?= csrf_field() ?><input type="hidden" name="action" value="checkout">
        <h3 style="margin-bottom:18px">Delivery details</h3>
        <div class="fgrid">
          <div class="fld"><label>Full name</label><input type="text" name="customer_name" required value="<?= e($user['name']) ?>"></div>
          <div class="fld"><label>Email address</label><input type="email" name="email" required value="<?= e($user['email']) ?>"></div>
          <div class="fld"><label>Mobile number</label><input type="text" name="mobile" required pattern="[0-9]{10}" value="<?= e($user['mobile']) ?>"></div>
          <div class="fld"><label>Pincode</label><input type="text" name="pincode" required pattern="[0-9]{6}" value="<?= e($user['pincode']) ?>"></div>
          <div class="fld full"><label>Billing address</label><input type="text" name="billing_address" required value="<?= e($user['address']) ?>" placeholder="House / street / landmark"></div>
          <div class="fld full">
            <label class="check"><input type="checkbox" name="same_as_billing" value="1" id="sameAddr" checked> Shipping address is the same as billing</label>
          </div>
          <div class="fld full" id="shipWrap" style="display:none"><label>Shipping address</label><input type="text" name="shipping_address" placeholder="Where should we deliver?"></div>
          <div class="fld"><label>City</label><input type="text" name="city" required value="<?= e($user['city']) ?>"></div>
          <div class="fld"><label>State</label><input type="text" name="state" required value="<?= e($user['state']) ?>"></div>
          <div class="fld full"><label>Order note <span class="hint">(optional)</span></label><input type="text" name="note" placeholder="Delivery instructions, gift message…"></div>
        </div>
      </div>

      <div class="panel" style="position:sticky;top:110px">
        <h3 style="margin-bottom:14px">Order summary</h3>
        <?php foreach ($items as $it): ?>
          <div style="display:flex;gap:12px;align-items:center;padding:9px 0;border-bottom:1px dashed var(--line)">
            <img src="<?= img_src($it['image'], $it['name'] ?? '', '') ?>" style="width:46px;height:46px;border-radius:6px;object-fit:cover" alt="" onerror="this.onerror=null;this.src='<?= e(art_fallback($it['name'] ?? '', '')) ?>';">
            <div style="flex:1;min-width:0">
              <div style="font-size:14px;color:var(--ink);overflow:hidden;text-overflow:ellipsis;white-space:nowrap"><?= e($it['name']) ?></div>
              <div class="hint">Qty <?= (int)$it['qty'] ?></div>
            </div>
            <b style="font-size:14px"><?= money($it['line_total']) ?></b>
          </div>
        <?php endforeach; ?>
        <div class="sum-row" style="margin-top:10px"><span>Subtotal</span><span><?= money($t['subtotal']) ?></span></div>
        <?php if ($t['discount'] > 0): ?>
          <div class="sum-row" style="color:var(--moss)"><span>Coupon (<?= e($t['coupon']) ?>)</span><span>− <?= money($t['discount']) ?></span></div>
        <?php endif; ?>
        <div class="sum-row"><span>Shipping</span>
          <span><?php if ($t['ship_saved'] > 0): ?><span style="color:var(--moss)">Free (<?= e($t['coupon']) ?>)</span><?php else: ?><?= $t['shipping'] > 0 ? money($t['shipping']) : 'Free' ?><?php endif; ?></span></div>
        <div class="sum-row"><span>Tax (<?= TAX_PERCENT ?>%)</span><span><?= money($t['tax']) ?></span></div>
        <div class="sum-row total"><span>Total</span><span><?= money($t['total']) ?></span></div>
        <button class="btn wide" style="margin-top:18px"><span>Continue to payment</span></button>
        <a class="btn wide ghost" style="margin-top:10px" href="<?= url(['page' => 'cart']) ?>"><span>Back to bag</span></a>
      </div>
    </div></form>
  </div></section>
<?php endif; ?>

<?php
/* ======================= PAGE: PAYMENT ======================= */
elseif ($page === 'payment'):
    require_login('checkout');
    $items = cart_detailed();
    $t     = cart_totals();
    $c     = $_SESSION['checkout'] ?? null;
    if (!$items || !$c) { redirect(url(['page' => 'cart'])); }
    if ($bad = cart_stock_issues()) {
        foreach ($bad as $b) flash($b, 'error');
        flash('Please fix your bag before paying.', 'error');
        redirect(url(['page' => 'cart']));
    }
?>
  <div class="phead"><div class="wrap">
    <div class="crumb"><a href="<?= url(['page' => 'checkout']) ?>">Checkout</a> / Payment</div>
    <h1 style="font-size:clamp(32px,5vw,52px)">Payment</h1>
  </div></div>
  <section class="sec"><div class="wrap">
    <div class="steps">
      <div class="step done"><div class="bar"></div><div class="dot">✓</div><div class="lb">Bag</div></div>
      <div class="step done"><div class="bar"></div><div class="dot">✓</div><div class="lb">Address</div></div>
      <div class="step on"><div class="bar"></div><div class="dot">3</div><div class="lb">Payment</div></div>
      <div class="step"><div class="dot">4</div><div class="lb">Done</div></div>
    </div>

    <form method="post"><div class="two-col">
      <div class="panel">
        <?= csrf_field() ?><input type="hidden" name="action" value="place_order">
        <h3 style="margin-bottom:6px">Choose a payment method</h3>
        <p class="hint" style="margin-bottom:18px">This is a demo gateway — no real money is charged.</p>

        <label class="pay-opt on" onclick="pickPay(this)">
          <input type="radio" name="payment_method" value="Cash on Delivery" checked>
          <span><b>Cash on Delivery</b><span>Pay the courier in cash when your order arrives.</span></span>
        </label>
        <label class="pay-opt" onclick="pickPay(this)">
          <input type="radio" name="payment_method" value="UPI">
          <span><b>UPI</b><span>Google Pay, PhonePe, Paytm or any UPI app.</span></span>
        </label>
        <label class="pay-opt" onclick="pickPay(this)">
          <input type="radio" name="payment_method" value="Credit / Debit Card">
          <span><b>Credit / Debit Card</b><span>Visa, Mastercard, RuPay and Amex accepted.</span></span>
        </label>
        <label class="pay-opt" onclick="pickPay(this)">
          <input type="radio" name="payment_method" value="Net Banking">
          <span><b>Net Banking</b><span>All major Indian banks supported.</span></span>
        </label>

        <div style="margin-top:24px;padding:18px;background:var(--sand);border-radius:var(--r-m)">
          <h4 style="font-family:'Jost';font-size:12px;letter-spacing:.12em;text-transform:uppercase;color:var(--mute);margin-bottom:8px">Delivering to</h4>
          <p style="font-size:14.5px;color:var(--ink)"><b><?= e($c['customer_name']) ?></b> · <?= e($c['mobile']) ?></p>
          <p style="font-size:14px"><?= e($c['shipping_address']) ?>, <?= e($c['city']) ?>, <?= e($c['state']) ?> — <?= e($c['pincode']) ?></p>
          <a class="link-u" style="display:inline-block;margin-top:10px" href="<?= url(['page' => 'checkout']) ?>">Edit address</a>
        </div>
      </div>

      <div class="panel" style="position:sticky;top:110px">
        <h3 style="margin-bottom:14px">You're paying</h3>
        <div class="sum-row"><span>Items (<?= cart_count() ?>)</span><span><?= money($t['subtotal']) ?></span></div>
        <?php if ($t['discount'] > 0): ?>
          <div class="sum-row" style="color:var(--moss)"><span>Coupon (<?= e($t['coupon']) ?>)</span><span>− <?= money($t['discount']) ?></span></div>
        <?php endif; ?>
        <div class="sum-row"><span>Shipping</span>
          <span><?php if ($t['ship_saved'] > 0): ?><span style="color:var(--moss)">Free (<?= e($t['coupon']) ?>)</span><?php else: ?><?= $t['shipping'] > 0 ? money($t['shipping']) : 'Free' ?><?php endif; ?></span></div>
        <div class="sum-row"><span>Tax (<?= TAX_PERCENT ?>%)</span><span><?= money($t['tax']) ?></span></div>
        <div class="sum-row total"><span>Amount payable</span><span><?= money($t['total']) ?></span></div>
        <button class="btn wide" style="margin-top:18px"><span>Place order</span></button>
        <p class="hint center" style="margin-top:12px">By placing this order you agree to our terms.</p>
      </div>
    </div></form>
  </div></section>

<?php
/* ======================= PAGE: ORDER SUCCESS ======================= */
elseif ($page === 'success'):
    require_login();
    $o = order_with_items((int)get('id', 0), (int)$user['id']);
    if (!$o) { redirect(url(['page' => 'orders'])); }
?>
  <section class="sec"><div class="wrap" style="max-width:820px">
    <div class="steps">
      <div class="step done"><div class="bar"></div><div class="dot">✓</div><div class="lb">Bag</div></div>
      <div class="step done"><div class="bar"></div><div class="dot">✓</div><div class="lb">Address</div></div>
      <div class="step done"><div class="bar"></div><div class="dot">✓</div><div class="lb">Payment</div></div>
      <div class="step on"><div class="dot">✓</div><div class="lb">Done</div></div>
    </div>

    <div class="panel center" style="padding:44px 32px">
      <div class="tick"><svg viewBox="0 0 24 24"><path d="M4 12.5l5 5L20 6.5"/></svg></div>
      <h1 style="font-size:clamp(30px,4.5vw,46px)">Thank you, <?= e(explode(' ', $o['customer_name'])[0]) ?>!</h1>
      <p class="lede" style="margin:10px auto 0">Your order has been placed successfully. A confirmation has been sent to <b><?= e($o['email']) ?></b>.</p>

      <div style="display:flex;gap:14px;justify-content:center;flex-wrap:wrap;margin:26px 0">
        <div style="background:var(--sand);padding:14px 22px;border-radius:var(--r-m)">
          <div class="hint">Order ID</div><b style="font-size:19px;color:var(--ink)">#<?= (int)$o['id'] ?></b></div>
        <div style="background:var(--sand);padding:14px 22px;border-radius:var(--r-m)">
          <div class="hint">Order date</div><b style="font-size:19px;color:var(--ink)"><?= date('d M Y', strtotime($o['created_at'])) ?></b></div>
        <div style="background:var(--sand);padding:14px 22px;border-radius:var(--r-m)">
          <div class="hint">Amount paid</div><b style="font-size:19px;color:var(--ink)"><?= money($o['total']) ?></b></div>
        <div style="background:var(--sand);padding:14px 22px;border-radius:var(--r-m)">
          <div class="hint">Payment</div><b style="font-size:19px;color:var(--ink)"><?= e($o['payment_method']) ?></b></div>
      </div>

      <div style="text-align:left;border-top:1px solid var(--line);padding-top:22px">
        <h3 style="margin-bottom:12px">Items in this order</h3>
        <?php foreach ($o['items'] as $it): ?>
          <div style="display:flex;gap:14px;align-items:center;padding:11px 0;border-bottom:1px dashed var(--line)">
            <img src="<?= img_src($it['product_image'], $it['product_name'] ?? '', '') ?>" style="width:56px;height:56px;border-radius:7px;object-fit:cover" alt="" onerror="this.onerror=null;this.src='<?= e(art_fallback($it['product_name'] ?? '', '')) ?>';">
            <div style="flex:1"><div style="color:var(--ink)"><?= e($it['product_name']) ?></div><div class="hint">Qty <?= (int)$it['qty'] ?> × <?= money($it['price']) ?></div></div>
            <b><?= money($it['line_total']) ?></b>
          </div>
        <?php endforeach; ?>
        <div style="max-width:300px;margin-left:auto;margin-top:14px">
          <div class="sum-row"><span>Subtotal</span><span><?= money($o['subtotal']) ?></span></div>
          <?php if ((float)($o['discount'] ?? 0) > 0): ?>
            <div class="sum-row" style="color:var(--moss)"><span>Coupon (<?= e($o['coupon_code'] ?? '') ?>)</span><span>− <?= money($o['discount']) ?></span></div>
          <?php endif; ?>
          <div class="sum-row"><span>Shipping</span><span><?= $o['shipping'] > 0 ? money($o['shipping']) : 'Free' ?></span></div>
          <div class="sum-row"><span>Tax</span><span><?= money($o['tax']) ?></span></div>
          <div class="sum-row total"><span>Total</span><span><?= money($o['total']) ?></span></div>
        </div>
        <h3 style="margin:22px 0 8px">Delivering to</h3>
        <p style="font-size:14.5px"><b><?= e($o['customer_name']) ?></b> · <?= e($o['mobile']) ?><br>
          <?= e($o['shipping_address']) ?>, <?= e($o['city']) ?>, <?= e($o['state']) ?> — <?= e($o['pincode']) ?></p>
      </div>

      <div style="display:flex;gap:12px;justify-content:center;flex-wrap:wrap;margin-top:28px">
        <a class="btn" href="<?= url(['page' => 'shop']) ?>"><span>Continue shopping</span></a>
        <a class="btn ghost" href="<?= url(['page' => 'orders']) ?>"><span>View order history</span></a>
        <form method="post">
          <?= csrf_field() ?><input type="hidden" name="action" value="reorder">
          <input type="hidden" name="order_id" value="<?= (int)$o['id'] ?>">
          <button class="btn ghost"><span>Reorder this later</span></button>
        </form>
      </div>
    </div>
  </div></section>
  <script>
  (function(){ /* confetti */
    const cols=['#B9603F','#C08B2E','#3E5C47','#241C19','#E7C0AC'];
    for(let i=0;i<40;i++){
      const p=document.createElement('div');
      p.style.cssText=`position:fixed;top:-12px;left:${Math.random()*100}vw;width:8px;height:14px;z-index:400;
        background:${cols[i%cols.length]};border-radius:${Math.random()>.5?'50%':'2px'};pointer-events:none;
        animation:fall ${1.6+Math.random()*1.4}s linear forwards`;
      document.body.appendChild(p); setTimeout(()=>p.remove(),3400);
    }
    const s=document.createElement('style');
    s.textContent='@keyframes fall{to{transform:translateY(105vh) rotate(620deg);opacity:.85}}';
    document.head.appendChild(s);
  })();
  </script>

<?php
/* ======================= PAGE: PROFILE ======================= */
elseif ($page === 'profile'):
    require_login('profile');
    $orders = user_orders((int)$user['id']);
    $spent  = 0; foreach ($orders as $o) if ($o['order_status'] !== 'Cancelled') $spent += (float)$o['total'];
?>
  <div class="phead"><div class="wrap">
    <div class="crumb"><a href="<?= url(['page' => 'home']) ?>">Home</a> / My account</div>
    <h1 style="font-size:clamp(32px,5vw,52px)">Hello, <?= e(explode(' ', $user['name'])[0]) ?></h1>
    <p class="lede">Member since <?= date('F Y', strtotime($user['created_at'])) ?></p>
  </div></div>
  <section class="sec"><div class="wrap">
    <div class="kpis" style="margin-bottom:26px">
      <div class="kpi"><div class="ic" style="background:var(--clay-lt);color:var(--clay-dk)">
        <svg viewBox="0 0 24 24"><rect x="3" y="5" width="18" height="16" rx="2"/><path d="M8 10h8"/></svg></div>
        <div><b><?= count($orders) ?></b><span>Total orders</span></div></div>
      <div class="kpi"><div class="ic" style="background:var(--moss-lt);color:var(--moss)">
        <svg viewBox="0 0 24 24"><path d="M12 1v22"/><path d="M17 5H9.5a3.5 3.5 0 000 7h5a3.5 3.5 0 010 7H6"/></svg></div>
        <div><b style="font-size:26px"><?= CURRENCY . number_format($spent) ?></b><span>Total spent</span></div></div>
      <div class="kpi"><div class="ic" style="background:var(--gold-lt);color:#8A6212">
        <svg viewBox="0 0 24 24"><path d="M20.8 5.6a5.5 5.5 0 00-7.8 0L12 6.6l-1-1a5.5 5.5 0 10-7.8 7.8l8.8 8.8 8.8-8.8a5.5 5.5 0 000-7.8z"/></svg></div>
        <div><b><?= count($wish) ?></b><span>Wishlist items</span></div></div>
      <div class="kpi"><div class="ic" style="background:#E7EDF5;color:#3B5A80">
        <svg viewBox="0 0 24 24"><path d="M6 2l1.5 3h9L18 2"/><rect x="3" y="6" width="18" height="15" rx="2"/></svg></div>
        <div><b><?= cart_count() ?></b><span>Items in bag</span></div></div>
    </div>

    <div class="two-col">
      <div class="panel">
        <h3 style="margin-bottom:18px">Profile details</h3>
        <form method="post">
          <?= csrf_field() ?><input type="hidden" name="action" value="profile_update">
          <div class="fgrid">
            <div class="fld"><label>Full name</label><input type="text" name="name" value="<?= e($user['name']) ?>" required></div>
            <div class="fld"><label>Email <span class="hint">(cannot change)</span></label><input type="email" value="<?= e($user['email']) ?>" disabled></div>
            <div class="fld"><label>Mobile number</label><input type="text" name="mobile" value="<?= e($user['mobile']) ?>"></div>
            <div class="fld"><label>Pincode</label><input type="text" name="pincode" value="<?= e($user['pincode']) ?>"></div>
            <div class="fld full"><label>Address</label><input type="text" name="address" value="<?= e($user['address']) ?>"></div>
            <div class="fld"><label>City</label><input type="text" name="city" value="<?= e($user['city']) ?>"></div>
            <div class="fld"><label>State</label><input type="text" name="state" value="<?= e($user['state']) ?>"></div>
          </div>
          <button class="btn sm" style="margin-top:18px"><span>Save profile</span></button>
        </form>
      </div>

      <div>
        <div class="panel" style="margin-bottom:18px">
          <h3 style="margin-bottom:16px">Change password</h3>
          <form method="post">
            <?= csrf_field() ?><input type="hidden" name="action" value="password_change">
            <div class="fld" style="margin-bottom:12px"><label>Current password</label><input type="password" name="old" required></div>
            <div class="fld" style="margin-bottom:16px"><label>New password</label><input type="password" name="new" minlength="6" required></div>
            <button class="btn sm wide"><span>Update password</span></button>
          </form>
        </div>
        <div class="panel">
          <h3 style="margin-bottom:12px">Quick links</h3>
          <div style="display:flex;flex-direction:column;gap:10px">
            <a class="btn sm ghost" href="<?= url(['page' => 'orders']) ?>"><span>Order history</span></a>
            <a class="btn sm ghost" href="<?= url(['page' => 'wishlist']) ?>"><span>My wishlist</span></a>
            <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="logout">
              <button class="btn sm wide dark"><span>Log out</span></button></form>
          </div>
        </div>
      </div>
    </div>
  </div></section>

<?php
/* ======================= PAGE: ORDER HISTORY ======================= */
elseif ($page === 'orders'):
    require_login('orders');
    $orders = user_orders((int)$user['id']);
?>
  <div class="phead"><div class="wrap">
    <div class="crumb"><a href="<?= url(['page' => 'profile']) ?>">My account</a> / Orders</div>
    <h1 style="font-size:clamp(32px,5vw,52px)">Order history</h1>
  </div></div>
  <section class="sec"><div class="wrap">
    <?php if (!$orders): ?>
      <div class="empty">
        <svg viewBox="0 0 24 24"><rect x="3" y="5" width="18" height="16" rx="2"/><path d="M8 10h8"/></svg>
        <h2>No orders yet</h2>
        <p class="lede center" style="margin:8px auto 20px">When you place an order it will appear here.</p>
        <a class="btn" href="<?= url(['page' => 'shop']) ?>"><span>Start shopping</span></a>
      </div>
    <?php else: ?>
      <div style="overflow-x:auto">
      <table class="tbl">
        <thead><tr><th>Order ID</th><th>Date</th><th>Items</th><th>Total</th><th>Payment</th><th>Status</th><th>Actions</th></tr></thead>
        <tbody>
        <?php foreach ($orders as $o): ?>
          <tr>
            <td><b>#<?= (int)$o['id'] ?></b></td>
            <td><?= date('d M Y', strtotime($o['created_at'])) ?></td>
            <td><?= (int)$o['item_count'] ?> item<?= (int)$o['item_count'] === 1 ? '' : 's' ?></td>
            <td><b><?= money($o['total']) ?></b></td>
            <td><?= e($o['payment_method']) ?><div class="hint"><?= e($o['payment_status']) ?></div></td>
            <td><span class="<?= status_class($o['order_status']) ?>"><?= e($o['order_status']) ?></span></td>
            <td><div class="row-acts" style="display:flex;gap:8px;flex-wrap:wrap;align-items:center">
              <a class="mini" href="<?= url(['page' => 'order_view', 'id' => $o['id']]) ?>">View details</a>
              <form method="post">
                <?= csrf_field() ?><input type="hidden" name="action" value="reorder">
                <input type="hidden" name="order_id" value="<?= (int)$o['id'] ?>">
                <button class="mini" title="Add every item from this order back to your bag">Reorder</button>
              </form>
            </div></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
      </div>
    <?php endif; ?>
  </div></section>

<?php
/* ======================= PAGE: ORDER DETAIL (customer) ======================= */
elseif ($page === 'order_view'):
    require_login('orders');
    $o = order_with_items((int)get('id', 0), (int)$user['id']);
    if (!$o) { redirect(url(['page' => 'orders'])); }
    $flow = ['Pending', 'Confirmed', 'Processing', 'Shipped', 'Delivered'];
    $curIdx = array_search($o['order_status'], $flow, true);
?>
  <div class="phead"><div class="wrap">
    <div class="crumb"><a href="<?= url(['page' => 'orders']) ?>">Orders</a> / #<?= (int)$o['id'] ?></div>
    <h1 style="font-size:clamp(30px,4.5vw,46px)">Order #<?= (int)$o['id'] ?></h1>
    <p class="lede">Placed on <?= date('d F Y \a\t H:i', strtotime($o['created_at'])) ?></p>
  </div></div>
  <section class="sec"><div class="wrap">
    <?php if ($o['order_status'] !== 'Cancelled'): ?>
      <div class="steps" style="margin-bottom:34px">
        <?php foreach ($flow as $i => $s): ?>
          <div class="step <?= $curIdx !== false && $i < $curIdx ? 'done' : ($i === $curIdx ? 'on' : '') ?>">
            <div class="bar"></div>
            <div class="dot"><?= $curIdx !== false && $i < $curIdx ? '✓' : $i + 1 ?></div>
            <div class="lb"><?= e($s) ?></div>
          </div>
        <?php endforeach; ?>
      </div>
    <?php else: ?>
      <div class="alert error">This order was cancelled. Contact support if this looks wrong.</div>
    <?php endif; ?>

    <div class="two-col">
      <div class="panel">
        <h3 style="margin-bottom:12px">Items</h3>
        <?php foreach ($o['items'] as $it): ?>
          <div style="display:flex;gap:14px;align-items:center;padding:13px 0;border-bottom:1px dashed var(--line)">
            <img src="<?= img_src($it['product_image'], $it['product_name'] ?? '', '') ?>" style="width:64px;height:64px;border-radius:8px;object-fit:cover" alt="" onerror="this.onerror=null;this.src='<?= e(art_fallback($it['product_name'] ?? '', '')) ?>';">
            <div style="flex:1">
              <a href="<?= url(['page' => 'product', 'id' => $it['product_id']]) ?>" style="color:var(--ink)"><?= e($it['product_name']) ?></a>
              <div class="hint">Qty <?= (int)$it['qty'] ?> × <?= money($it['price']) ?></div>
            </div>
            <b><?= money($it['line_total']) ?></b>
          </div>
        <?php endforeach; ?>
      </div>
      <div class="panel">
        <h3 style="margin-bottom:12px">Summary</h3>
        <div class="sum-row"><span>Subtotal</span><span><?= money($o['subtotal']) ?></span></div>
        <?php if ((float)($o['discount'] ?? 0) > 0): ?>
          <div class="sum-row" style="color:var(--moss)"><span>Coupon (<?= e($o['coupon_code'] ?? '') ?>)</span><span>− <?= money($o['discount']) ?></span></div>
        <?php elseif (!empty($o['coupon_code'])): ?>
          <div class="sum-row" style="color:var(--moss)"><span>Coupon</span><span><?= e($o['coupon_code']) ?> · free shipping</span></div>
        <?php endif; ?>
        <div class="sum-row"><span>Shipping</span><span><?= $o['shipping'] > 0 ? money($o['shipping']) : 'Free' ?></span></div>
        <div class="sum-row"><span>Tax</span><span><?= money($o['tax']) ?></span></div>
        <div class="sum-row total"><span>Total</span><span><?= money($o['total']) ?></span></div>
        <h3 style="margin:22px 0 8px">Delivery address</h3>
        <p style="font-size:14.5px"><b><?= e($o['customer_name']) ?></b> · <?= e($o['mobile']) ?><br>
          <?= e($o['shipping_address']) ?><br><?= e($o['city']) ?>, <?= e($o['state']) ?> — <?= e($o['pincode']) ?></p>
        <h3 style="margin:22px 0 8px">Payment</h3>
        <p style="font-size:14.5px"><?= e($o['payment_method']) ?> · <span class="<?= status_class($o['payment_status']) ?>"><?= e($o['payment_status']) ?></span></p>
        <form method="post" style="margin-top:18px">
          <?= csrf_field() ?><input type="hidden" name="action" value="reorder">
          <input type="hidden" name="order_id" value="<?= (int)$o['id'] ?>">
          <button class="btn wide"><span>Reorder these items</span></button>
        </form>
        <p class="hint center" style="margin-top:9px">Everything still in stock goes straight back into your bag.</p>
        <a class="btn wide ghost" style="margin-top:12px" href="<?= url(['page' => 'orders']) ?>"><span>Back to orders</span></a>
      </div>
    </div>
  </div></section>

<?php
/* ======================= PAGE: ABOUT ======================= */
elseif ($page === 'about'): ?>
  <div class="phead"><div class="wrap">
    <div class="crumb"><a href="<?= url(['page' => 'home']) ?>">Home</a> / About us</div>
    <h1 style="font-size:clamp(32px,5vw,52px)">About <?= e(SITE_NAME) ?></h1>
    <p class="lede">A small Indian beauty studio making honest, barrier-first formulations.</p>
  </div></div>
  <section class="sec"><div class="wrap">
    <div class="two-col" style="grid-template-columns:1fr 1fr;gap:44px;align-items:center">
      <div class="rv" style="border-radius:var(--r-l);overflow:hidden;box-shadow:var(--sh-2)">
        <img src="<?= collage_art() ?>" alt="Our studio">
      </div>
      <div class="rv">
        <div class="eyebrow">Our story</div>
        <h2 style="margin:10px 0 16px">Beauty without the theatre</h2>
        <p style="margin-bottom:14px"><?= e(SITE_NAME) ?> started in 2023 in Ahmedabad, out of a simple frustration — most products sold on packaging and promises rather than on what was actually inside the bottle. We wanted formulas with percentages printed on the label and ingredient lists short enough to read.</p>
        <p>Every product is developed with a cosmetic chemist, tested dermatologically on Indian skin tones and climate, and made in small batches so nothing sits in a warehouse losing potency.</p>
      </div>
    </div>

    <div class="cat-grid" style="margin-top:56px;grid-template-columns:repeat(auto-fit,minmax(260px,1fr))">
      <div class="panel rv"><div class="eyebrow">Mission</div>
        <h3 style="margin:8px 0 8px">Effective, affordable, honest</h3>
        <p>To make clinically-backed skincare and colour available at a price a college student can afford, without hiding behind vague claims like “brightening complex”.</p></div>
      <div class="panel rv"><div class="eyebrow">Vision</div>
        <h3 style="margin:8px 0 8px">A label you can trust</h3>
        <p>To become the beauty brand Indian customers reach for first — because the ingredient list, the percentage and the price are all stated plainly.</p></div>
      <div class="panel rv"><div class="eyebrow">What we make</div>
        <h3 style="margin:8px 0 8px">Four focused edits</h3>
        <p>Skincare, makeup, haircare and fragrance — around forty products in total. We would rather perfect a few than launch a hundred.</p></div>
    </div>

    <div class="offer rv" style="margin-top:56px">
      <div>
        <div class="eyebrow" style="color:#E7C0AC">By the numbers</div>
        <h2>Small brand, real numbers</h2>
        <p>Because trust is built on specifics, not adjectives.</p>
      </div>
      <div class="right">
        <div class="countdown">
          <div class="cd-box"><b>40+</b><span>Products</span></div>
          <div class="cd-box"><b>62K</b><span>Customers</span></div>
          <div class="cd-box"><b>4.6</b><span>Avg rating</span></div>
          <div class="cd-box"><b>0</b><span>Animal tests</span></div>
        </div>
      </div>
    </div>
  </div></section>

<?php
/* ======================= PAGE: CONTACT ======================= */
elseif ($page === 'contact'): ?>
  <div class="phead"><div class="wrap">
    <div class="crumb"><a href="<?= url(['page' => 'home']) ?>">Home</a> / Contact us</div>
    <h1 style="font-size:clamp(32px,5vw,52px)">Get in touch</h1>
    <p class="lede">Questions about an order, an ingredient or a shade match — we usually reply within one working day.</p>
  </div></div>
  <section class="sec"><div class="wrap">
    <div class="two-col">
      <div class="panel">
        <h3 style="margin-bottom:18px">Send us a message</h3>
        <form method="post">
          <?= csrf_field() ?><input type="hidden" name="action" value="contact_send">
          <div class="fgrid">
            <div class="fld"><label>Your name</label><input type="text" name="name" required value="<?= e($user['name'] ?? '') ?>"></div>
            <div class="fld"><label>Email address</label><input type="email" name="email" required value="<?= e($user['email'] ?? '') ?>"></div>
            <div class="fld full"><label>Subject</label><input type="text" name="subject" placeholder="Order #, product name or topic"></div>
            <div class="fld full"><label>Message</label><textarea name="message" required placeholder="How can we help?"></textarea></div>
          </div>
          <button class="btn" style="margin-top:18px"><span>Send message</span></button>
        </form>
      </div>
      <div>
        <div class="panel" style="margin-bottom:18px">
          <h3 style="margin-bottom:14px">Contact information</h3>
          <div style="display:flex;flex-direction:column;gap:14px">
            <div><div class="eyebrow">Studio address</div><p style="font-size:14.5px">2nd Floor, Iscon Emporio, Satellite Road<br>Ahmedabad, Gujarat 380015, India</p></div>
            <div><div class="eyebrow">Phone</div><p style="font-size:14.5px">+91 79 4000 1234 · Mon–Sat, 10am–7pm</p></div>
            <div><div class="eyebrow">Email</div><p style="font-size:14.5px">care@lumea.example · press@lumea.example</p></div>
            <div><div class="eyebrow">Support</div><p style="font-size:14.5px">Average reply time: under 12 hours</p></div>
          </div>
        </div>
        <div class="panel">
          <h3 style="margin-bottom:12px">Frequently asked</h3>
          <div class="spec-list">
            <li><b>Where is my order?</b><span>Track it under Order history in your account.</span></li>
            <li><b>Do you ship pan-India?</b><span>Yes — 3 to 5 working days everywhere.</span></li>
            <li><b>Are products tested on animals?</b><span>Never. Every product is cruelty-free.</span></li>
          </div>
        </div>
      </div>
    </div>
  </div></section>

<?php
/* ======================= PAGE: LOGIN ======================= */
elseif ($page === 'login'):
    if ($user) redirect(url(['page' => 'profile']));
?>
  <section class="sec"><div class="wrap" style="max-width:470px">
    <div class="panel">
      <div class="center" style="margin-bottom:24px">
        <div class="eyebrow">Welcome back</div>
        <h1 style="font-size:40px;margin-top:6px">Log in</h1>
      </div>
      <form method="post">
        <?= csrf_field() ?><input type="hidden" name="action" value="login">
        <div class="fld" style="margin-bottom:14px"><label>Email address</label>
          <input type="email" name="email" required value="demo@lumea.com"></div>
        <div class="fld" style="margin-bottom:20px"><label>Password</label>
          <input type="password" name="password" required placeholder="demo123"></div>
        <button class="btn wide"><span>Log in</span></button>
      </form>
      <p class="hint center" style="margin-top:16px">Demo account — <b>demo@lumea.com</b> / <b>demo123</b></p>
      <p class="center" style="margin-top:18px;font-size:14px">New to <?= e(SITE_NAME) ?>?
        <a class="link-u" href="<?= url(['page' => 'register']) ?>">Create an account</a></p>
    </div>
  </div></section>

<?php
/* ======================= PAGE: REGISTER ======================= */
elseif ($page === 'register'):
    if ($user) redirect(url(['page' => 'profile']));
?>
  <section class="sec"><div class="wrap" style="max-width:640px">
    <div class="panel">
      <div class="center" style="margin-bottom:24px">
        <div class="eyebrow">Join us</div>
        <h1 style="font-size:40px;margin-top:6px">Create your account</h1>
        <p class="lede" style="margin:8px auto 0">Faster checkout, order tracking and 10% off your first order.</p>
      </div>
      <form method="post" id="regForm">
        <?= csrf_field() ?><input type="hidden" name="action" value="register">
        <div class="fgrid">
          <div class="fld"><label>Full name</label><input type="text" name="name" required></div>
          <div class="fld"><label>Email address</label><input type="email" name="email" required></div>
          <div class="fld"><label>Mobile number</label><input type="text" name="mobile" pattern="[0-9]{10}" required placeholder="10 digits"></div>
          <div class="fld"><label>Pincode <span class="hint">(optional)</span></label><input type="text" name="pincode"></div>
          <div class="fld"><label>Password</label><input type="password" name="password" id="pw" minlength="6" required></div>
          <div class="fld"><label>Confirm password</label><input type="password" name="cpassword" id="pw2" required></div>
          <div class="fld full"><label>Address <span class="hint">(optional)</span></label><input type="text" name="address"></div>
          <div class="fld full">
            <div class="ship-bar"><i id="pwBar" style="width:0;background:var(--danger)"></i></div>
            <span class="hint" id="pwTxt">Password strength</span>
          </div>
        </div>
        <button class="btn wide" style="margin-top:18px"><span>Create account</span></button>
      </form>
      <p class="center" style="margin-top:18px;font-size:14px">Already registered?
        <a class="link-u" href="<?= url(['page' => 'login']) ?>">Log in instead</a></p>
    </div>
  </div></section>

<?php else: ?>
  <div class="empty wrap">
    <h2>Page not found</h2>
    <p class="lede center" style="margin:8px auto 20px">The page you were looking for doesn't exist.</p>
    <a class="btn" href="<?= url(['page' => 'home']) ?>"><span>Back to home</span></a>
  </div>
<?php endif; ?>
</main>

<?php /* =================== QUICK VIEW MODAL =================== */ ?>
<div class="modal-bg" id="qvBg">
  <div class="modal" id="qvBox"><button class="modal-x" onclick="closeQV()">✕</button><div id="qvBody" style="padding:30px"></div></div>
</div>

<footer class="site">
  <div class="wrap">
    <div class="f-grid">
      <div>
        <div class="logo"><?= e(SITE_NAME) ?><small><?= e(SITE_TAG) ?></small></div>
        <p style="margin-top:14px">Barrier-first skincare, breathable colour and honest labels — made in small batches in Ahmedabad, India.</p>
        <div class="socials">
          <a href="#" aria-label="Instagram"><svg viewBox="0 0 24 24"><rect x="3" y="3" width="18" height="18" rx="5"/><circle cx="12" cy="12" r="4"/><circle cx="17.5" cy="6.5" r="1"/></svg></a>
          <a href="#" aria-label="Facebook"><svg viewBox="0 0 24 24"><path d="M15 3h-3a4 4 0 00-4 4v3H5v4h3v7h4v-7h3l1-4h-4V7a1 1 0 011-1h3z"/></svg></a>
          <a href="#" aria-label="X"><svg viewBox="0 0 24 24"><path d="M4 4l16 16M20 4L4 20"/></svg></a>
          <a href="#" aria-label="YouTube"><svg viewBox="0 0 24 24"><rect x="2" y="5" width="20" height="14" rx="4"/><path d="M10 9l5 3-5 3z"/></svg></a>
        </div>
      </div>
      <div><h4>Shop</h4><ul>
        <?php foreach ($navCats as $c): ?>
          <li><a href="<?= url(['page' => 'shop', 'cat' => $c['id']]) ?>"><?= e($c['name']) ?></a></li>
        <?php endforeach; ?>
        <li><a href="<?= url(['page' => 'shop']) ?>">All products</a></li>
      </ul></div>
      <div><h4>Account</h4><ul>
        <li><a href="<?= url(['page' => $user ? 'profile' : 'login']) ?>"><?= $user ? 'My profile' : 'Log in' ?></a></li>
        <li><a href="<?= url(['page' => $user ? 'orders' : 'register']) ?>"><?= $user ? 'Order history' : 'Create account' ?></a></li>
        <li><a href="<?= url(['page' => 'cart']) ?>">Shopping bag</a></li>
        <li><a href="<?= url(['page' => 'wishlist']) ?>">Wishlist</a></li>
      </ul></div>
      <div><h4>Company</h4><ul>
        <li><a href="<?= url(['page' => 'about']) ?>">About us</a></li>
        <li><a href="<?= url(['page' => 'contact']) ?>">Contact us</a></li>
        <li><a href="<?= url(['page' => 'categories']) ?>">Categories</a></li>
        <li><a href="<?= url(['page' => 'admin_login']) ?>">Admin panel</a></li>
      </ul></div>
    </div>
    <div class="f-bot">
      <span>© <?= date('Y') ?> <?= e(SITE_NAME) ?>. Built for the Advanced Web Development in PHP assignment.</span>
      <span>Cash on delivery · UPI · Cards · Net banking</span>
    </div>
  </div>
</footer>

<script>
/* ================= TOASTS ================= */
function toast(msg,type){
  const w=document.getElementById('toasts');
  const el=document.createElement('div');
  el.className='toast'+(type==='error'?' err':'');
  el.innerHTML='<b>'+(type==='error'?'!':'✓')+'</b><span></span>';
  el.querySelector('span').textContent=msg;
  w.appendChild(el);
  setTimeout(()=>{el.classList.add('out');setTimeout(()=>el.remove(),350)},3200);
}
setTimeout(()=>document.querySelectorAll('.toast').forEach(t=>{t.classList.add('out');setTimeout(()=>t.remove(),350)}),3600);

/* ================= NAV ================= */
document.getElementById('burger')?.addEventListener('click',()=>document.getElementById('menu').classList.toggle('open'));
window.addEventListener('scroll',()=>{
  document.getElementById('siteHead')?.classList.toggle('shrunk',window.scrollY>10);
});

/* ================= REVEAL ON SCROLL ================= */
const io=new IntersectionObserver(es=>es.forEach(en=>{if(en.isIntersecting){en.target.classList.add('in');io.unobserve(en.target)}}),{threshold:.12});
document.querySelectorAll('.rv').forEach(el=>io.observe(el));

/* ================= HERO SLIDER ================= */
(function(){
  const slides=document.querySelectorAll('.hero .slide');
  if(!slides.length) return;
  const dots=document.querySelectorAll('#hDots button');
  let i=0,timer=null;
  function go(n){
    slides[i].classList.remove('on'); dots[i]?.classList.remove('on');
    i=(n+slides.length)%slides.length;
    slides[i].classList.add('on'); dots[i]?.classList.add('on');
  }
  function play(){ timer=setInterval(()=>go(i+1),6000); }
  function stop(){ clearInterval(timer); }
  dots.forEach(d=>d.addEventListener('click',()=>{stop();go(+d.dataset.i);play()}));
  document.getElementById('hNext')?.addEventListener('click',()=>{stop();go(i+1);play()});
  document.getElementById('hPrev')?.addEventListener('click',()=>{stop();go(i-1);play()});
  document.getElementById('hero')?.addEventListener('mouseenter',stop);
  document.getElementById('hero')?.addEventListener('mouseleave',play);
  play();
})();

/* ================= COUNTDOWN ================= */
(function(){
  const box=document.getElementById('cd'); if(!box) return;
  const end=new Date(); end.setDate(end.getDate()+5); end.setHours(23,59,59,0);
  function pad(n){return String(n).padStart(2,'0')}
  function tick(){
    let s=Math.max(0,Math.floor((end-new Date())/1000));
    document.getElementById('cdD').textContent=pad(Math.floor(s/86400));
    document.getElementById('cdH').textContent=pad(Math.floor(s%86400/3600));
    document.getElementById('cdM').textContent=pad(Math.floor(s%3600/60));
    document.getElementById('cdS').textContent=pad(s%60);
  }
  tick(); setInterval(tick,1000);
})();

/* ================= QTY STEPPER ================= */
function stepQty(btn,dir,autoSubmit){
  const inp=btn.parentElement.querySelector('input');
  let v=parseInt(inp.value||'1',10)+dir;
  const min=parseInt(inp.min||'1',10), max=parseInt(inp.max||'99',10);
  if(v<min)v=min;
  if(v>max){
    v=max;
    /* stock ceiling reached — tell the shopper instead of silently ignoring the click */
    const stock=parseInt(inp.dataset.stock||'0',10);
    if(dir>0&&stock>0){
      toast('Only '+stock+' '+(stock===1?'piece':'pieces')+' of "'+(inp.dataset.name||'this item')+'" in stock.','error');
    }
  }
  inp.value=v;
  if(autoSubmit){ /* leave for Update bag button */ }
}

/* ================= COUPONS ================= */
function toggleOffers(btn){
  const box=document.getElementById('cpnList');
  if(!box) return;
  const open=box.style.display!=='none';
  box.style.display=open?'none':'flex';
  btn.textContent=open?btn.textContent.replace('Hide','View'):btn.textContent.replace('View','Hide');
}
function removeItem(id){
  document.getElementById('rmId').value=id;
  document.getElementById('rmForm').submit();
}

/* ================= AJAX ADD TO CART ================= */
function addToCart(id,qty,btn){
  const fd=new FormData();
  fd.append('action','ajax_add'); fd.append('product_id',id); fd.append('qty',qty||1);
  if(btn){btn.disabled=true;}
  fetch('<?= basename($_SERVER['SCRIPT_NAME']) ?>',{method:'POST',body:fd})
    .then(r=>r.json())
    .then(d=>{
      if(btn)btn.disabled=false;
      if(!d.ok){toast(d.msg,'error');return;}
      const b=document.getElementById('cartBadge');
      b.textContent=d.count; b.style.display='grid';
      b.classList.remove('bump'); void b.offsetWidth; b.classList.add('bump');
      toast(d.msg, d.warn?'error':'success');
    })
    .catch(()=>{if(btn)btn.disabled=false;toast('Something went wrong.','error')});
}

/* ================= WISHLIST ================= */
function toggleWish(id,btn){
  const fd=new FormData(); fd.append('action','ajax_wish'); fd.append('product_id',id);
  fetch('<?= basename($_SERVER['SCRIPT_NAME']) ?>',{method:'POST',body:fd})
    .then(r=>r.json())
    .then(d=>{
      btn?.classList.toggle('active',d.added);
      const w=document.getElementById('wishBadge');
      if(w){w.textContent=d.count; w.style.display=d.count?'grid':'none';
        w.classList.remove('bump'); void w.offsetWidth; w.classList.add('bump');}
      toast(d.msg);
    });
}

/* ================= QUICK VIEW ================= */
function quickView(id){
  const bg=document.getElementById('qvBg');
  document.getElementById('qvBody').innerHTML='<p style="text-align:center;padding:40px;color:#8B7D74">Loading…</p>';
  bg.classList.add('on'); document.body.style.overflow='hidden';
  fetch('<?= basename($_SERVER['SCRIPT_NAME']) ?>?page=quickview&id='+id)
    .then(r=>r.text()).then(h=>{document.getElementById('qvBody').innerHTML=h;});
}
function closeQV(){document.getElementById('qvBg').classList.remove('on');document.body.style.overflow=''}
document.getElementById('qvBg')?.addEventListener('click',e=>{if(e.target.id==='qvBg')closeQV()});
document.addEventListener('keydown',e=>{if(e.key==='Escape')closeQV()});

/* ================= PRODUCT TABS ================= */
document.querySelectorAll('.tabs-h button').forEach(b=>{
  b.addEventListener('click',()=>{
    document.querySelectorAll('.tabs-h button').forEach(x=>x.classList.remove('on'));
    document.querySelectorAll('.tabpane').forEach(x=>x.classList.remove('on'));
    b.classList.add('on');
    document.getElementById('tab-'+b.dataset.tab).classList.add('on');
  });
});

/* ================= CHECKOUT: SHIPPING TOGGLE ================= */
document.getElementById('sameAddr')?.addEventListener('change',function(){
  document.getElementById('shipWrap').style.display=this.checked?'none':'flex';
});

/* ================= PAYMENT: OPTION HIGHLIGHT ================= */
function pickPay(el){
  document.querySelectorAll('.pay-opt').forEach(p=>p.classList.remove('on'));
  el.classList.add('on');
  el.querySelector('input').checked=true;
}

/* ================= PASSWORD STRENGTH ================= */
(function(){
  const pw=document.getElementById('pw'); if(!pw) return;
  const bar=document.getElementById('pwBar'), txt=document.getElementById('pwTxt');
  pw.addEventListener('input',()=>{
    const v=pw.value; let s=0;
    if(v.length>=6)s++; if(v.length>=10)s++;
    if(/[A-Z]/.test(v)&&/[a-z]/.test(v))s++;
    if(/[0-9]/.test(v))s++; if(/[^A-Za-z0-9]/.test(v))s++;
    const pct=[0,22,44,62,82,100][s];
    const lbl=['Too short','Weak','Fair','Good','Strong','Very strong'][s];
    const col=['#A63A2E','#A63A2E','#C08B2E','#C08B2E','#3E5C47','#3E5C47'][s];
    bar.style.width=pct+'%'; bar.style.background=col; txt.textContent='Password strength: '+lbl;
  });
  document.getElementById('regForm')?.addEventListener('submit',e=>{
    if(document.getElementById('pw').value!==document.getElementById('pw2').value){
      e.preventDefault(); toast('Passwords do not match.','error');
    }
  });
})();
</script>
</body>
</html>
<?php
/* ======================================================================
   PRODUCT CARD COMPONENT  (declared last — PHP hoists function decls)
   ====================================================================== */
function product_card(array $p, array $wish = []): void {
    $disc   = discount_percent($p);
    $isNew  = strtotime((string)$p['created_at']) > strtotime('-7 day');
    $out    = (int)$p['stock'] < 1;
    $inWish = in_array((int)$p['id'], array_map('intval', $wish), true);
    ?>
    <article class="p-card rv">
      <div class="p-media">
        <a href="<?= url(['page' => 'product', 'id' => $p['id']]) ?>">
          <img src="<?= img_src($p['image'], $p['name'] ?? '', $p['category_slug'] ?? '') ?>" alt="<?= e($p['name']) ?>" loading="lazy"
               onerror="this.onerror=null;this.src='<?= e(art_fallback($p['name'] ?? '', $p['category_slug'] ?? '')) ?>';">
        </a>
        <div class="p-tags">
          <?php if ($out): ?><span class="tag out">Sold out</span>
          <?php else: ?>
            <?php if ($disc): ?><span class="tag"><?= $disc ?>% off</span><?php endif; ?>
            <?php if ($isNew): ?><span class="tag new">New</span><?php endif; ?>
          <?php endif; ?>
        </div>
        <div class="p-actions">
          <button class="<?= $inWish ? 'active' : '' ?>" title="Wishlist" onclick="toggleWish(<?= (int)$p['id'] ?>,this)">
            <svg viewBox="0 0 24 24"><path d="M20.8 5.6a5.5 5.5 0 00-7.8 0L12 6.6l-1-1a5.5 5.5 0 10-7.8 7.8l8.8 8.8 8.8-8.8a5.5 5.5 0 000-7.8z"/></svg>
          </button>
          <button title="Quick view" onclick="quickView(<?= (int)$p['id'] ?>)">
            <svg viewBox="0 0 24 24"><path d="M1.5 12S5.5 5 12 5s10.5 7 10.5 7-4 7-10.5 7S1.5 12 1.5 12z"/><circle cx="12" cy="12" r="3"/></svg>
          </button>
        </div>
        <div class="p-cart">
          <button class="btn wide sm" <?= $out ? 'disabled' : '' ?> onclick="addToCart(<?= (int)$p['id'] ?>,1,this)">
            <span><?= $out ? 'Sold out' : 'Add to bag' ?></span>
          </button>
        </div>
      </div>
      <div class="p-body">
        <div class="p-cat"><?= e($p['category_name'] ?? '') ?></div>
        <h3 class="p-title"><a href="<?= url(['page' => 'product', 'id' => $p['id']]) ?>"><?= e($p['name']) ?></a></h3>
        <div class="rate-row"><?= star_html((float)$p['rating']) ?><span><?= number_format((float)$p['rating'], 1) ?></span></div>
        <p class="p-desc"><?= e(mb_substr((string)$p['short_desc'], 0, 68)) ?><?= mb_strlen((string)$p['short_desc']) > 68 ? '…' : '' ?></p>
        <div class="p-price">
          <span class="price-now"><?= money(final_price($p)) ?></span>
          <?php if ($disc): ?>
            <span class="price-was"><?= money($p['price']) ?></span>
            <span class="save">Save <?= money((float)$p['price'] - final_price($p)) ?></span>
          <?php endif; ?>
        </div>
      </div>
    </article>
    <?php
}
