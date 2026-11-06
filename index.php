<?php
require_once __DIR__ . '/app.php';

$search = $_GET['search'] ?? '';
$category = $_GET['category'] ?? '';
$sort = $_GET['sort'] ?? 'featured';
$search = is_string($search) ? trim(substr($search, 0, 80)) : '';
$category = is_string($category) ? trim(substr($category, 0, 60)) : '';
$sort = is_string($sort) ? $sort : 'featured';
$categories = array_values(array_unique(array_column(products(), 'category')));
$catalogue = products(['search' => $search, 'category' => $category, 'sort' => $sort]);
$featured = products(['sort' => 'featured']);

site_shell_start('A brighter kind of everyday', 'home-page');
?>
    <section class="hero-section" aria-labelledby="hero-title">
        <div class="hero-content">
            <p class="hero-kicker"><span aria-hidden="true"></span> A considered collection for real life</p>
            <h1 id="hero-title">A little more<br><em>you.</em></h1>
            <p class="hero-description">Good technology should fit your life, not the other way around. Meet the pieces that make everyday feel a little more extraordinary.</p>
            <a class="button button-dark hero-button" href="#shop">Find your next favourite <span aria-hidden="true">↗</span></a>
            <p class="hero-footnote">Thoughtfully chosen. Yours to enjoy.</p>
        </div>
        <div class="hero-visual">
            <div class="hero-glow" aria-hidden="true"></div>
            <img class="hero-product" src="assets/hero-headphones.svg" alt="Sculptural wireless headphones in a soft lilac studio">
            <div class="hero-caption">
                <span class="caption-symbol" aria-hidden="true">✳</span>
                <span><strong>Find your frequency.</strong><small>Explore the Aero collection</small></span>
                <span class="caption-arrow" aria-hidden="true">↗</span>
            </div>
            <span class="hero-index" aria-hidden="true">01 / 04</span>
        </div>
    </section>

    <section class="benefit-strip" aria-label="Shopping with CloudCart">
        <p><span aria-hidden="true">✳</span> Curated with care</p>
        <p><span aria-hidden="true">↗</span> Free delivery over £100</p>
        <p><span aria-hidden="true">♡</span> 30 days to find your fit</p>
    </section>

    <section class="catalogue-section" id="shop" aria-labelledby="shop-title">
        <div class="section-heading">
            <div>
                <p class="section-eyebrow">THE GOOD STUFF</p>
                <h2 id="shop-title"><?= $search !== '' ? 'A good match.' : 'Made for your everyday.' ?></h2>
                <p class="section-description">Useful, lovely things. Nothing you don’t need.</p>
            </div>
            <span class="result-count"><?= count($catalogue) ?> considered finds</span>
        </div>

        <form class="catalogue-controls" method="get" action="index.php" role="search">
            <label class="search-field">
                <span class="visually-hidden">Search products</span>
                <span class="search-icon" aria-hidden="true">⌕</span>
                <input type="search" name="search" value="<?= e($search) ?>" placeholder="Find something lovely">
            </label>
            <?php if ($category !== ''): ?><input type="hidden" name="category" value="<?= e($category) ?>"><?php endif; ?>
            <label class="sort-select">
                <span class="visually-hidden">Sort products</span>
                <select name="sort">
                    <option value="featured" <?= $sort === 'featured' ? 'selected' : '' ?>>Our picks</option>
                    <option value="price-low" <?= $sort === 'price-low' ? 'selected' : '' ?>>Price: low to high</option>
                    <option value="price-high" <?= $sort === 'price-high' ? 'selected' : '' ?>>Price: high to low</option>
                    <option value="name" <?= $sort === 'name' ? 'selected' : '' ?>>Name: A to Z</option>
                </select>
            </label>
            <button class="filter-submit" type="submit">Apply</button>
        </form>

        <div class="category-list" aria-label="Filter by category">
            <?php
            $allHref = 'index.php' . ($search !== '' ? '?search=' . rawurlencode($search) : '');
            ?>
            <a class="category-chip <?= $category === '' ? 'is-selected' : '' ?>" href="<?= e($allHref) ?>" <?= $category === '' ? 'aria-current="page"' : '' ?>>Everything</a>
            <?php foreach ($categories as $itemCategory): ?>
                <?php
                $query = array_filter(['category' => $itemCategory, 'search' => $search, 'sort' => $sort]);
                $href = 'index.php?' . http_build_query($query);
                ?>
                <a class="category-chip <?= $category === $itemCategory ? 'is-selected' : '' ?>" href="<?= e($href) ?>" <?= $category === $itemCategory ? 'aria-current="page"' : '' ?>><?= e($itemCategory) ?></a>
            <?php endforeach; ?>
        </div>

        <?php if ($catalogue === []): ?>
            <div class="empty-state">
                <span class="empty-symbol" aria-hidden="true">⌕</span>
                <h3>No finds this time.</h3>
                <p>Try another search or take a look at everything we’ve picked.</p>
                <a class="text-link" href="index.php#shop">See all of the good stuff <span aria-hidden="true">↗</span></a>
            </div>
        <?php else: ?>
            <div class="product-grid">
                <?php foreach ($catalogue as $product): ?>
                    <?php
                    $image = (string) $product['image'];
                    if (!preg_match('#^assets/[a-zA-Z0-9._-]+\.svg$#', $image) || !is_file(__DIR__ . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $image))) {
                        $image = 'assets/hero-headphones.svg';
                    }
                    ?>
                    <article class="product-card">
                        <a class="product-art product-art-<?= e(strtolower((string) $product['category'])) ?>" href="product.php?id=<?= (int) $product['id'] ?>" aria-label="View <?= e($product['name']) ?>">
                            <?php if ((bool) $product['is_featured']): ?><span class="product-badge">A favourite</span><?php endif; ?>
                            <img src="<?= e($image) ?>" alt="" loading="lazy">
                            <span class="product-quick-view">Take a closer look <span aria-hidden="true">↗</span></span>
                        </a>
                        <div class="product-details">
                            <div>
                                <p class="product-category"><?= e($product['category']) ?></p>
                                <h3><a href="product.php?id=<?= (int) $product['id'] ?>"><?= e($product['name']) ?></a></h3>
                            </div>
                            <p class="product-price"><?= money((float) $product['price']) ?></p>
                        </div>
                        <div class="product-footer">
                            <p class="stock-note"><?= (int) $product['stock'] < 1 ? 'A little moment out of stock' : ((int) $product['stock'] < 5 ? 'Only a few left' : 'Ready when you are') ?></p>
                            <form action="cart.php" method="post">
                                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                                <input type="hidden" name="action" value="add">
                                <input type="hidden" name="product_id" value="<?= (int) $product['id'] ?>">
                                <button class="add-button" type="submit" <?= (int) $product['stock'] < 1 ? 'disabled' : '' ?>>
                                    <span aria-hidden="true">+</span> <?= (int) $product['stock'] < 1 ? 'Unavailable' : 'Add to bag' ?>
                                </button>
                            </form>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>

    <section class="story-section" id="story" aria-labelledby="story-title">
        <div class="story-copy">
            <p class="section-eyebrow">A BETTER KIND OF BUYING</p>
            <h2 id="story-title">Less, but<br><em>better.</em></h2>
            <p>We look for the thoughtful details: things that feel good to use, work beautifully, and earn the space they take up. No endless scrolling. Just the good stuff.</p>
            <a class="text-link" href="#shop">Get to know the collection <span aria-hidden="true">↗</span></a>
        </div>
        <div class="story-art">
            <img src="assets/hero-headphones.svg" alt="Our featured headphones, designed for quiet focus and everyday listening" loading="lazy">
            <span class="story-note"><span aria-hidden="true">✳</span> Considered, not crowded.</span>
        </div>
    </section>

    <section class="closing-note" aria-label="A note from CloudCart">
        <span aria-hidden="true">✳</span>
        <p>Here’s to finding the things that just feel right.</p>
        <a href="#shop">Take a look around <span aria-hidden="true">↗</span></a>
    </section>
<?php site_shell_end(); ?>
