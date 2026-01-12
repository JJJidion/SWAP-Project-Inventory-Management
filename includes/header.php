<!-- Navigation Bar Component -->
<nav class="navbar">
    <div class="container">
        <h1>Inventory Manager</h1>
        <ul>
            <?php if (isset($_SESSION["username"])): ?>
                <?php if ($_SESSION["role"] === "User"): ?>
                    <li><a href="<?php echo BASE_URL; ?>/pages/index_user.php">Home</a></li>
                <?php elseif ($_SESSION["role"] === "Admin"): ?>
                    <li><a href="<?php echo BASE_URL; ?>/pages/index_admin.php">Home</a></li>
                <?php endif; ?>
                <li><a href="<?php echo BASE_URL; ?>/pages/profile.php">Profile</a></li>
                <li><a href="<?php echo BASE_URL; ?>/pages/logout.php">Log Out</a></li>
            <?php endif; ?>
        </ul>
    </div>
</nav>