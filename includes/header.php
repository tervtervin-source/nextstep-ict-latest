<nav style="background-color: #1e1b4b; border-bottom: 2px solid #581c87; padding: 0.75rem 1.5rem;">
    <div style="max-width: 1100px; margin: 0 auto; display: flex; align-items: center; justify-content: space-between;">
        <a href="index.php" style="color: #ffffff; font-weight: 700; font-size: 1.125rem; text-decoration: none; display: flex; align-items: center; gap: 0.5rem;">
            <i class="fa-solid fa-graduation-cap" style="color: #d97706;"></i> NextStep ICT
        </a>
        <div style="display: flex; gap: 1rem; align-items: center;">
            <?php if (isset($_SESSION['user_id'])): ?>
                <a href="logout.php" style="color: #e2e8f0; font-size: 0.875rem; text-decoration: none; font-weight: 500;">
                    <i class="fa-solid fa-right-from-bracket"></i> Logout
                </a>
            <?php else: ?>
                <a href="login.php" style="color: #e2e8f0; font-size: 0.875rem; text-decoration: none; font-weight: 500;">
                    <i class="fa-solid fa-right-to-bracket"></i> Login
                </a>
                <a href="register.php" style="background-color: #d97706; color: #ffffff; padding: 0.4rem 0.8rem; border-radius: 6px; font-size: 0.875rem; text-decoration: none; font-weight: 600;">
                    Register
                </a>
            <?php endif; ?>
        </div>
    </div>
</nav>