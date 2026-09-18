<?php
declare(strict_types=1);

namespace Tests\Integration\Community;

use Tests\Helpers\DatabaseTestCase;

require_once PROJECT_ROOT . '/community/users/user_functions.php';

final class PasswordResetTest extends DatabaseTestCase
{
    private function giveResetToken(int $userId): string
    {
        $token = bin2hex(random_bytes(32));
        $this->pdo->prepare('UPDATE community_users SET reset_token = ?, reset_token_expiry = NOW() + INTERVAL 1 HOUR WHERE id = ?')
            ->execute([$token, $userId]);
        return $token;
    }

    public function test_reset_signs_out_remember_me_cookies(): void
    {
        $userId = $this->seedCommunityUser();
        $stolenCookie = generate_remember_token($userId);
        $this->assertNotFalse(validate_remember_token($stolenCookie));

        $this->assertTrue(reset_password($this->giveResetToken($userId), 'N3w-Passw0rd!'));

        $this->assertFalse(validate_remember_token($stolenCookie));
        $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM remember_tokens WHERE user_id = ?');
        $stmt->execute([$userId]);
        $this->assertSame(0, (int) $stmt->fetchColumn());
    }

    public function test_failed_reset_leaves_remember_me_cookies_alone(): void
    {
        $userId = $this->seedCommunityUser();
        $cookie = generate_remember_token($userId);

        $this->assertFalse(reset_password('not-a-real-token', 'N3w-Passw0rd!'));
        $this->assertNotFalse(validate_remember_token($cookie));
    }

    /**
     * Pre-fills the address's bucket rather than sending real reset emails to
     * reach the cap.
     */
    public function test_flooded_address_gets_no_new_reset_link(): void
    {
        $email = 'flood_' . bin2hex(random_bytes(4)) . '@example.test';
        $userId = $this->seedCommunityUser(null, $email);
        for ($i = 0; $i < rate_limit_max('community_password_reset_email'); $i++) {
            rate_limit_record('community_password_reset_email', $email, PASSWORD_RESET_EMAIL_PREFIX);
        }

        $this->assertFalse(request_password_reset(strtoupper($email)));

        $stmt = $this->pdo->prepare('SELECT reset_token FROM community_users WHERE id = ?');
        $stmt->execute([$userId]);
        $this->assertNull($stmt->fetchColumn());
    }
}
