<?php
require_once __DIR__ . '/app.php';

$search = $_GET['search'] ?? '';
$category = $_GET['category'] ?? '';
$sort = $_GET['sort'] ?? 'featured';
$search = is_string($search) ? trim(substr($search, 0, 80)) : '';
$category = is_string($category) ? trim(substr($category, 0, 60)) : '';
$sort = is_string($sort) ? $sort : 'featured';

$allProducts = products(['sort' => 'featured']);
$categories = array_values(array_unique(array_column($allProducts, 'category')));
$catalogue = products(['search' => $search, 'category' => $category, 'sort' => $sort]);
$featuredDeals = array_slice(array_values(array_filter($allProducts, static fn(array $p): bool => (bool) $p['is_featured'])), 0, 6);
if (count($featuredDeals) < 4) {
    $featuredDeals = array_slice($allProducts, 0, 6);
}

site_shell_start('UK Tech, Computing, Gaming & Audio', 'home-page');
?>
    <section class="hero-banner" aria-labelledby="hero-title">
        <div class="hero-banner-inner">
            <div class="hero-copy-block">
                <div class="hero-pill-row">
                    <span class="hero-live-badge"><i aria-hidden="true">⚡</i> UK TECH EVENT · UP TO 25% OFF</span>
                    <span class="hero-timer" data-deal-countdown>Ends in 05h : 42m : 18s</span>
                </div>
                <h1 id="hero-title">Next-Gen Tech.<br><span>Delivered Tomorrow.</span></h1>
                <p class="hero-description">Shop flagship creator laptops, 240Hz QD-OLED monitors, spatial ANC audio, and pro esports gear — with <strong>FREE Next-Day UK Prime Delivery</strong> on orders over £100.</p>
                <div class="hero-cta-group">
                    <a class="button button-amber" href="#shop">Shop All Tech Deals <span aria-hidden="true">→</span></a>
                    <a class="button button-glass" href="#deals">Explore Lightning Deals</a>
                </div>
                <div class="hero-trust-row">
                    <span>✓ <strong>Prime UK:</strong> Free Next-Day over £100</span>
                    <span>✓ <strong>2-Year</strong> Hardware Warranty</span>
                    <span>✓ <strong>30-Day</strong> Hassle-Free Returns</span>
                </div>
            </div>

            <div class="hero-showcase-card">
                <span class="showcase-tag">SPOTLIGHT DEAL · SAVE 21%</span>
                <img class="hero-product-photo" src="https://images.unsplash.com/photo-1517336714731-489689fd1ca8?auto=format&fit=crop&w=1000&q=85" alt="NovaBook Pro 14 Creator Laptop and studio workspace">
                <div class="showcase-overlay">
                    <div>
                        <p class="showcase-cat">COMPUTING FLAGSHIP</p>
                        <h2>NovaBook Pro 14" Creator</h2>
                        <p class="showcase-stars">★★★★★ <span>4.9 (1,842 verified ratings)</span></p>
                    </div>
                    <div class="showcase-price-box">
                        <small>RRP £1,399.00</small>
                        <strong>£1,149.00</strong>
                        <a href="home.php?category=Computing#shop">Shop Now →</a>
                    </div>
                </div>
            </div>
        </div>

        <div class="bento-quad-grid" aria-label="Featured department collections">
            <article class="bento-card">
                <h3>Pro Computing &amp; Displays</h3>
                <div class="bento-mini-grid">
                    <a href="home.php?category=Computing#shop">
                        <img src="https://images.unsplash.com/photo-1517336714731-489689fd1ca8?auto=format&fit=crop&w=400&q=80" alt="Creator Laptops" loading="lazy">
                        <span>Creator Laptops</span>
                    </a>
                    <a href="home.php?category=Computing#shop">
                        <img src="https://images.unsplash.com/photo-1527443224154-c4a3942d3acf?auto=format&fit=crop&w=400&q=80" alt="OLED Monitors" loading="lazy">
                        <span>240Hz Monitors</span>
                    </a>
                    <a href="home.php?category=Computing#shop">
                        <img src="https://images.unsplash.com/photo-1587829741301-dc798b83add3?auto=format&fit=crop&w=400&q=80" alt="Mechanical Keyboards" loading="lazy">
                        <span>Custom Keyboards</span>
                    </a>
                    <a href="home.php?category=Accessories#shop">
                        <img src="https://images.unsplash.com/photo-1597872200969-2b65d56bd16b?auto=format&fit=crop&w=400&q=80" alt="NVMe Portable SSDs" loading="lazy">
                        <span>NVMe Storage</span>
                    </a>
                </div>
                <a class="bento-link" href="home.php?category=Computing#shop">Shop Computing deals →</a>
            </article>

            <article class="bento-card">
                <h3>Esports &amp; Next-Gen Gaming</h3>
                <div class="bento-mini-grid">
                    <a href="home.php?category=Gaming#shop">
                        <img src="https://images.unsplash.com/photo-1622979135225-d2ba269cf1ac?auto=format&fit=crop&w=400&q=80" alt="Mixed Reality VR" loading="lazy">
                        <span>4K VR Headsets</span>
                    </a>
                    <a href="home.php?category=Gaming#shop">
                        <img src="https://images.unsplash.com/photo-1600080972464-8e5f35f63d08?auto=format&fit=crop&w=400&q=80" alt="Pro Controllers" loading="lazy">
                        <span>Pro Controllers</span>
                    </a>
                    <a href="home.php?category=Gaming#shop">
                        <img src="https://images.unsplash.com/photo-1615663245857-ac93bb7c39e7?auto=format&fit=crop&w=400&q=80" alt="Superlight Gaming Mice" loading="lazy">
                        <span>54g Esports Mice</span>
                    </a>
                    <a href="home.php?category=Audio#shop">
                        <img src="https://images.unsplash.com/photo-1590602847861-f357a9332bbc?auto=format&fit=crop&w=400&q=80" alt="Streaming Microphones" loading="lazy">
                        <span>Streaming Mics</span>
                    </a>
                </div>
                <a class="bento-link" href="home.php?category=Gaming#shop">Explore Gaming gear →</a>
            </article>

            <article class="bento-card">
                <h3>Studio Audio &amp; Spatial Sound</h3>
                <div class="bento-mini-grid">
                    <a href="home.php?category=Audio#shop">
                        <img src="https://images.unsplash.com/photo-1505740420928-5e560c06d30e?auto=format&fit=crop&w=400&q=80" alt="ANC Headphones" loading="lazy">
                        <span>ANC Headphones</span>
                    </a>
                    <a href="home.php?category=Audio#shop">
                        <img src="https://images.unsplash.com/photo-1590658268037-6bf12165a8df?auto=format&fit=crop&w=400&q=80" alt="Wireless Earbuds" loading="lazy">
                        <span>Wireless Buds</span>
                    </a>
                    <a href="home.php?category=Audio#shop">
                        <img src="https://images.unsplash.com/photo-1608043152269-423dbba4e7e1?auto=format&fit=crop&w=400&q=80" alt="Smart Speakers" loading="lazy">
                        <span>Spatial Speakers</span>
                    </a>
                    <a href="home.php?category=Audio#shop">
                        <img src="https://images.unsplash.com/photo-1590602847861-f357a9332bbc?auto=format&fit=crop&w=400&q=80" alt="XLR Studio Mics" loading="lazy">
                        <span>Studio XLR Mics</span>
                    </a>
                </div>
                <a class="bento-link" href="home.php?category=Audio#shop">See all Audio →</a>
            </article>

            <article class="bento-card">
                <h3>Creator Gear &amp; Smart Living</h3>
                <div class="bento-mini-grid">
                    <a href="home.php?category=Photography#shop">
                        <img src="https://images.unsplash.com/photo-1507582020474-9a35b7d455d9?auto=format&fit=crop&w=400&q=80" alt="4K Camera Drones" loading="lazy">
                        <span>4K Pro Drones</span>
                    </a>
                    <a href="home.php?category=Photography#shop">
                        <img src="https://images.unsplash.com/photo-1526170375885-4d8ecf77b99f?auto=format&fit=crop&w=400&q=80" alt="Instant Cameras" loading="lazy">
                        <span>Hybrid Cameras</span>
                    </a>
                    <a href="home.php?category=Wearables#shop">
                        <img src="https://images.unsplash.com/photo-1523275335684-37898b6baf30?auto=format&fit=crop&w=400&q=80" alt="Smartwatches" loading="lazy">
                        <span>Titanium Watches</span>
                    </a>
                    <a href="home.php?category=Accessories#shop">
                        <img src="https://images.unsplash.com/photo-1586816879360-004f5b0c51e3?auto=format&fit=crop&w=400&q=80" alt="Qi2 Fast Chargers" loading="lazy">
                        <span>Qi2 Charging</span>
                    </a>
                </div>
                <a class="bento-link" href="home.php?category=Photography#shop">Discover Creator tech →</a>
            </article>
        </div>
    </section>

    <section class="deals-strip-section" id="deals" aria-labelledby="deals-heading">
        <div class="section-heading">
            <div>
                <span class="deal-pill-header">🔥 TODAY’S LIGHTNING DEALS</span>
                <h2 id="deals-heading">Top-Rated Tech at Limited-Time UK Prices</h2>
            </div>
            <a class="text-link" href="#shop">View all <?= count($allProducts) ?> products →</a>
        </div>

        <div class="deals-carousel">
            <?php foreach ($featuredDeals as $dealProduct): ?>
                <?php
                $dealImage = product_image_url((string) $dealProduct['image'], (string) $dealProduct['name']);
                $dealMeta = product_meta($dealProduct);
                ?>
                <article class="deal-card">
                    <a class="deal-card-media" href="product.php?id=<?= (int) $dealProduct['id'] ?>">
                        <span class="deal-discount-tag">-<?= (int) $dealMeta['discount_pct'] ?>%</span>
                        <img src="<?= e($dealImage) ?>" alt="<?= e($dealProduct['name']) ?>" loading="lazy">
                    </a>
                    <div class="deal-card-body">
                        <div class="deal-badges-line">
                            <span class="deal-limited-badge">Limited time deal</span>
                        </div>
                        <div class="deal-price-line">
                            <strong><?= money((float) $dealProduct['price']) ?></strong>
                            <small>RRP: <s><?= money((float) $dealMeta['rrp']) ?></s></small>
                        </div>
                        <h3><a href="product.php?id=<?= (int) $dealProduct['id'] ?>"><?= e($dealProduct['name']) ?></a></h3>
                        <p class="deal-rating">★★★★★ <span><?= e($dealMeta['rating']) ?> (<?= e($dealMeta['reviews']) ?>)</span></p>
                        <div class="deal-claim-bar" aria-label="<?= (int) $dealMeta['claimed_pct'] ?>% claimed">
                            <div class="deal-claim-fill" style="width: <?= (int) $dealMeta['claimed_pct'] ?>%"></div>
                        </div>
                        <p class="deal-claim-text"><?= (int) $dealMeta['claimed_pct'] ?>% claimed · <span>✓ Prime Tomorrow</span></p>
                        <form action="cart.php" method="post">
                            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                            <input type="hidden" name="action" value="add">
                            <input type="hidden" name="product_id" value="<?= (int) $dealProduct['id'] ?>">
                            <button class="button-add-amber" type="submit" <?= (int) $dealProduct['stock'] < 1 ? 'disabled' : '' ?>>
                                <?= (int) $dealProduct['stock'] < 1 ? 'Out of Stock' : 'Add to Basket' ?>
                            </button>
                        </form>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>
    </section>

    <section class="catalogue-section" id="shop" aria-labelledby="shop-title">
        <div class="section-heading">
            <div>
                <p class="section-eyebrow">CLOUDCART UK STOREFRONT</p>
                <h2 id="shop-title">
                    <?php if ($search !== ''): ?>
                        Results for “<?= e($search) ?>”
                    <?php elseif ($category !== ''): ?>
                        <?= e($category) ?> Department
                    <?php else: ?>
                        Featured Tech &amp; Customer Favourites
                    <?php endif; ?>
                </h2>
                <p class="section-description">Every order ships with tracked UK delivery and 2-year hardware cover.</p>
            </div>
            <span class="result-count">Showing <strong><?= count($catalogue) ?></strong> of <?= count($allProducts) ?> products</span>
        </div>

        <form class="catalogue-controls" method="get" action="home.php#shop" role="search">
            <label class="search-field">
                <span class="visually-hidden">Filter products</span>
                <span class="search-icon" aria-hidden="true">⌕</span>
                <input type="search" name="search" value="<?= e($search) ?>" placeholder="Filter by product name, spec, or category…">
            </label>
            <?php if ($category !== ''): ?><input type="hidden" name="category" value="<?= e($category) ?>"><?php endif; ?>
            <label class="sort-select">
                <span class="sort-label">Sort by:</span>
                <select name="sort" aria-label="Sort products">
                    <option value="featured" <?= $sort === 'featured' ? 'selected' : '' ?>>Featured &amp; Best Sellers</option>
                    <option value="price-low" <?= $sort === 'price-low' ? 'selected' : '' ?>>Price: Low to High</option>
                    <option value="price-high" <?= $sort === 'price-high' ? 'selected' : '' ?>>Price: High to Low</option>
                    <option value="name" <?= $sort === 'name' ? 'selected' : '' ?>>Name: A to Z</option>
                </select>
            </label>
            <button class="filter-submit" type="submit">Apply Filter</button>
            <?php if ($search !== '' || $category !== ''): ?>
                <a class="filter-reset" href="home.php#shop">Clear all ✕</a>
            <?php endif; ?>
        </form>

        <div class="category-list" aria-label="Filter by department">
            <?php
            $allHref = 'home.php' . ($search !== '' ? '?search=' . rawurlencode($search) : '') . '#shop';
            ?>
            <a class="category-chip <?= $category === '' ? 'is-selected' : '' ?>" href="<?= e($allHref) ?>" <?= $category === '' ? 'aria-current="page"' : '' ?>>All Departments (<?= count($allProducts) ?>)</a>
            <?php foreach ($categories as $itemCategory): ?>
                <?php
                $query = array_filter(['category' => $itemCategory, 'search' => $search, 'sort' => $sort]);
                $href = 'home.php?' . http_build_query($query) . '#shop';
                ?>
                <a class="category-chip <?= $category === $itemCategory ? 'is-selected' : '' ?>" href="<?= e($href) ?>" <?= $category === $itemCategory ? 'aria-current="page"' : '' ?>><?= e($itemCategory) ?></a>
            <?php endforeach; ?>
        </div>

        <?php if ($catalogue === []): ?>
            <div class="empty-state">
                <span class="empty-symbol" aria-hidden="true">⌕</span>
                <h3>No matching tech products found.</h3>
                <p>Try clearing your search filter or browse all departments in our UK catalogue.</p>
                <a class="button button-amber" href="home.php#shop">Show All Products →</a>
            </div>
        <?php else: ?>
            <div class="product-grid">
                <?php foreach ($catalogue as $product): ?>
                    <?php
                    $image = product_image_url((string) $product['image'], (string) $product['name']);
                    $meta = product_meta($product);
                    $stock = (int) $product['stock'];
                    $price = (float) $product['price'];
                    ?>
                    <article class="product-card">
                        <a class="product-art" href="product.php?id=<?= (int) $product['id'] ?>" aria-label="View <?= e($product['name']) ?>">
                            <span class="product-badge <?= e($meta['badge_class']) ?>"><?= e($meta['badge']) ?></span>
                            <img src="<?= e($image) ?>" alt="<?= e($product['name']) ?>" loading="lazy">
                            <span class="product-quick-view">Inspect Specs &amp; Reviews ↗</span>
                        </a>
                        <div class="product-card-content">
                            <div class="product-meta-top">
                                <span class="product-category"><?= e($product['category']) ?></span>
                                <span class="product-rating" aria-label="Rated <?= e($meta['rating']) ?> out of 5">
                                    <span class="stars-gold">★★★★★</span>
                                    <strong><?= e($meta['rating']) ?></strong>
                                    <small>(<?= e($meta['reviews']) ?>)</small>
                                </span>
                            </div>

                            <h3 class="product-title">
                                <a href="product.php?id=<?= (int) $product['id'] ?>"><?= e($product['name']) ?></a>
                            </h3>

                            <p class="product-snippet"><?= e($product['description']) ?></p>

                            <div class="product-price-block">
                                <div class="price-main-row">
                                    <span class="price-discount">-<?= (int) $meta['discount_pct'] ?>%</span>
                                    <span class="product-price"><?= money($price) ?></span>
                                </div>
                                <p class="price-rrp">RRP: <s><?= money((float) $meta['rrp']) ?></s> · Save <?= money((float) $meta['savings']) ?></p>
                            </div>

                            <div class="product-delivery-meta">
                                <p class="prime-delivery-line">
                                    <span class="prime-tick">✓ prime</span>
                                    <strong><?= $price >= 100 ? 'FREE Delivery Tomorrow' : 'Fast UK Dispatch' ?></strong>
                                </p>
                                <p class="stock-note <?= $stock < 1 ? 'stock-out' : ($stock < 10 ? 'stock-low' : 'stock-ok') ?>">
                                    <?= $stock < 1 ? 'Currently out of stock' : ($stock < 10 ? 'Only ' . $stock . ' left in stock — order soon' : 'In Stock · Dispatched by CloudCart UK') ?>
                                </p>
                            </div>

                            <div class="product-footer-actions">
                                <form action="cart.php" method="post" class="add-cart-form">
                                    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                                    <input type="hidden" name="action" value="add">
                                    <input type="hidden" name="product_id" value="<?= (int) $product['id'] ?>">
                                    <button class="button-add-amber" type="submit" <?= $stock < 1 ? 'disabled' : '' ?>>
                                        <?= $stock < 1 ? 'Unavailable' : 'Add to Basket' ?>
                                    </button>
                                </form>
                                <a class="button-details-ghost" href="product.php?id=<?= (int) $product['id'] ?>">Details</a>
                            </div>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>

    <section class="story-section" id="story" aria-labelledby="story-title">
        <div class="story-copy">
            <p class="section-eyebrow">CLOUD-NATIVE COMMERCE ARCHITECTURE</p>
            <h2 id="story-title">Built for speed.<br><span>Backed by Azure.</span></h2>
            <p>CloudCart combines real-time inventory locking in Azure SQL, serverless order notifications via Python Azure Functions, and TOTP multi-factor security — engineered for instant, reliable tech shopping.</p>
            <div class="story-pillars">
                <div>
                    <strong>⚡ Instant Dispatch</strong>
                    <span>Free Next-Day UK delivery on orders over £100</span>
                </div>
                <div>
                    <strong>🔒 Bank-Grade Security</strong>
                    <span>Argon2id hashing, CSRF protection &amp; RFC 6238 MFA</span>
                </div>
            </div>
            <a class="button button-amber" href="#shop">Browse the Full Catalogue →</a>
        </div>
        <div class="story-art">
            <img src="https://images.unsplash.com/photo-1527443224154-c4a3942d3acf?auto=format&fit=crop&w=1000&q=85" alt="UltraWide OLED monitor and pro creator desk setup" loading="lazy">
            <span class="story-note">✓ Verified UK Stock · 2-Year Warranty Included</span>
        </div>
    </section>
<?php site_shell_end(); ?>
