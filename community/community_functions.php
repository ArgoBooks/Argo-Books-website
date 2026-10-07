<?php
require_once __DIR__ . '/../email_sender.php';
require_once __DIR__ . '/../email_marketing.php';

/**
 * Get all posts with vote counts, ordered by creation date (newest first)
 * 
 * @return array Array of posts
 */
function get_all_posts()
{
    global $pdo;

    // Join with users table to get avatar
    $stmt = $pdo->query('SELECT p.*, u.avatar FROM community_posts p 
                         LEFT JOIN community_users u ON p.user_id = u.id 
                         ORDER BY p.created_at DESC');

    $posts = [];
    while ($row = $stmt->fetch()) {
        $posts[] = $row;
    }

    return $posts;
}

/**
 * Get a single post by ID
 * 
 * @param int $post_id Post ID
 * @return array|false Post data or false if not found
 */
function get_post($post_id)
{
    global $pdo;

    // Join with users table to get avatar
    $stmt = $pdo->prepare('SELECT p.*, u.avatar FROM community_posts p 
                         LEFT JOIN community_users u ON p.user_id = u.id 
                         WHERE p.id = ?');
    $stmt->execute([$post_id]);
    $post = $stmt->fetch();

    return $post;
}

/**
 * Add a new post with @mentions support
 * 
 * @param int $user_id User ID
 * @param string $user_name User's name
 * @param string $user_email User's email
 * @param string $title Post title
 * @param string $content Post content
 * @param string $post_type Post type ('bug' or 'feature')
 * @return int|false New post ID or false on failure
 */
function add_post($user_id, $user_name, $user_email, $title, $content, $post_type)
{
    global $pdo;

    // Process @mentions if the mentions.php file exists
    $has_mentions = false;
    $mentions = [];

    if (file_exists(__DIR__ . '/mentions/mentions.php')) {
        require_once __DIR__ . '/mentions/mentions.php';

        // Extract mentions from content
        $mentions = extract_mentions($content);
        $has_mentions = !empty($mentions);
    }

    $stmt = $pdo->prepare('INSERT INTO community_posts 
        (user_id, user_name, user_email, title, content, post_type, views) 
        VALUES (?, ?, ?, ?, ?, ?, 0)');

    if ($stmt->execute([$user_id, $user_name, $user_email, $title, $content, $post_type])) {
        $post_id = $pdo->lastInsertId();

        // Create notifications for mentioned users if applicable
        if ($has_mentions && function_exists('create_mention_notifications')) {
            create_mention_notifications($mentions, $post_id, 0, $user_id);

            // Email each mentioned user (skipping the author themselves).
            // Gated by community_digest preference inside send_mention_email().
            foreach (array_unique($mentions) as $mention_username) {
                $mentioned_id = function_exists('get_user_id_by_username')
                    ? get_user_id_by_username($mention_username)
                    : null;
                if (!$mentioned_id || (int) $mentioned_id === (int) $user_id) {
                    continue;
                }
                send_mention_email((int) $mentioned_id, (int) $post_id, 0, $user_name, $title, $content);
            }
        }

        send_notification_email('new_post', [
            'id' => $post_id,
            'user_name' => $user_name,
            'user_email' => $user_email,
            'title' => $title,
            'content' => $content,
            'post_type' => $post_type
        ]);

        return $post_id;
    }

    return false;
}

/**
 * Update a post's status
 * 
 * @param int $post_id Post ID
 * @param string $status New status ('open', 'in_progress', 'completed', 'declined')
 * @return bool Success status
 */
function update_post_status($post_id, $status)
{
    global $pdo;

    $stmt = $pdo->prepare('UPDATE community_posts SET status = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?');
    $result = $stmt->execute([$status, $post_id]);

    return $result;
}

/**
 * Get all comments for a post
 * 
 * @param int $post_id Post ID
 * @return array Array of comments
 */
function get_post_comments($post_id)
{
    global $pdo;

    // Join with users table to get avatar
    $stmt = $pdo->prepare('SELECT c.*, u.avatar FROM community_comments c 
                         LEFT JOIN community_users u ON c.user_id = u.id 
                         WHERE c.post_id = ? ORDER BY c.created_at ASC');
    $stmt->execute([$post_id]);

    $comments = [];
    while ($row = $stmt->fetch()) {
        $comments[] = $row;
    }

    return $comments;
}

/**
 * Get comment count for a post
 * 
 * @param int $post_id Post ID
 * @return int Comment count
 */
function get_comment_count($post_id)
{
    global $pdo;

    $stmt = $pdo->prepare('SELECT COUNT(*) as count FROM community_comments WHERE post_id = ?');
    $stmt->execute([$post_id]);
    $row = $stmt->fetch();
    $count = $row['count'];

    return $count;
}

/**
 * Add a new comment with @mentions support
 * 
 * @param int $post_id Post ID
 * @param string $user_name User's name
 * @param string $user_email User's email
 * @param string $content Comment content
 * @param int $user_id User ID (optional)
 * @return array|false New comment data or false on failure
 */
function add_comment($post_id, $user_name, $user_email, $content, $user_id = null)
{
    global $pdo;

    // Process @mentions if the mentions.php file exists
    $has_mentions = false;
    $mentions = [];

    if (file_exists(__DIR__ . '/mentions/mentions.php')) {
        require_once __DIR__ . '/mentions/mentions.php';

        // Extract mentions from content
        $mentions = extract_mentions($content);
        $has_mentions = !empty($mentions);
    }

    $stmt = $pdo->prepare('INSERT INTO community_comments (post_id, user_name, user_email, content, votes, user_id) 
                         VALUES (?, ?, ?, ?, 0, ?)');

    if ($stmt->execute([$post_id, $user_name, $user_email, $content, $user_id])) {
        $comment_id = $pdo->lastInsertId();

        // Get the post data
        $post = get_post($post_id);

        // Tracks who has been emailed for this comment, so an author mentioned in a reply on their own post gets one email, not two.
        $emailed_user_ids = [];

        // Create notifications for mentioned users if applicable
        if ($has_mentions && function_exists('create_mention_notifications')) {
            create_mention_notifications($mentions, $post_id, $comment_id, $user_id);

            foreach (array_unique($mentions) as $mention_username) {
                $mentioned_id = function_exists('get_user_id_by_username')
                    ? get_user_id_by_username($mention_username)
                    : null;
                if (!$mentioned_id || (int) $mentioned_id === (int) $user_id) {
                    continue;
                }
                send_mention_email((int) $mentioned_id, (int) $post_id, (int) $comment_id, $user_name, $post['title'] ?? '', $content);
                $emailed_user_ids[(int) $mentioned_id] = true;
            }
        }

        // Email the post author about the new reply, unless they ARE the
        // commenter or were already emailed via the @mention path above.
        if ($post && !empty($post['user_id'])) {
            $post_author_id = (int) $post['user_id'];
            if ($post_author_id !== (int) $user_id && !isset($emailed_user_ids[$post_author_id])) {
                send_post_reply_email($post_author_id, (int) $post_id, (int) $comment_id, $user_name, $post['title'] ?? '', $content);
            }
        }

        // Get the new comment
        $stmt = $pdo->prepare('SELECT * FROM community_comments WHERE id = ?');
        $stmt->execute([$comment_id]);
        $new_comment = $stmt->fetch();

        send_notification_email('new_comment', [
            'id' => $comment_id,
            'post_id' => $post_id,
            'post_title' => $post['title'],
            'user_name' => $user_name,
            'user_email' => $user_email,
            'content' => $content
        ]);

        // Process the comment content for display if needed
        if ($has_mentions && function_exists('process_mentions')) {
            $new_comment['processed_content'] = process_mentions(htmlspecialchars($content));
        } else {
            $new_comment['processed_content'] = htmlspecialchars($content);
        }

        return $new_comment;
    }

    return false;
}

/**
 * Add or update a vote
 * 
 * @param int $post_id Post ID
 * @param string $user_email User's email
 * @param int $vote_type Vote type (1 for upvote, -1 for downvote)
 * @return array|false New vote count and user's vote or false on failure
 */
function vote_post($post_id, $user_email, $vote_type)
{
    global $pdo;

    // Check if user has already voted
    $stmt = $pdo->prepare('SELECT vote_type FROM community_votes WHERE post_id = ? AND user_email = ?');
    $stmt->execute([$post_id, $user_email]);
    $existing_vote = $stmt->fetch();

    if ($existing_vote) {
        // User already voted, check if they're changing their vote
        if ($existing_vote['vote_type'] == $vote_type) {
            // Remove the vote (cancel)
            $stmt = $pdo->prepare('DELETE FROM community_votes WHERE post_id = ? AND user_email = ?');
            $stmt->execute([$post_id, $user_email]);

            $user_vote = 0;
        } else {
            $stmt = $pdo->prepare('UPDATE community_votes SET vote_type = ?, created_at = CURRENT_TIMESTAMP 
                                 WHERE post_id = ? AND user_email = ?');
            $stmt->execute([$vote_type, $post_id, $user_email]);

            $user_vote = $vote_type;
        }
    } else {
        // Add new vote
        $stmt = $pdo->prepare('INSERT INTO community_votes (post_id, user_email, vote_type) 
                             VALUES (?, ?, ?)');
        $stmt->execute([$post_id, $user_email, $vote_type]);

        $user_vote = $vote_type;
    }

    // Update vote count in posts table
    $stmt = $pdo->prepare('SELECT SUM(vote_type) as total_votes FROM community_votes WHERE post_id = ?');
    $stmt->execute([$post_id]);
    $row = $stmt->fetch();

    $total_votes = $row['total_votes'] ?? 0;

    // Update the post's vote count
    $stmt = $pdo->prepare('UPDATE community_posts SET votes = ? WHERE id = ?');
    $stmt->execute([$total_votes, $post_id]);

    return [
        'new_vote_count' => $total_votes,
        'user_vote' => $user_vote
    ];
}

/**
 * Get user's vote for a post
 * 
 * @param int $post_id Post ID
 * @param string $user_email User's email
 * @return int|false Vote type (1, -1) or 0 if no vote, or false on failure
 */
function get_user_vote($post_id, $user_email)
{
    global $pdo;

    $stmt = $pdo->prepare('SELECT vote_type FROM community_votes WHERE post_id = ? AND user_email = ?');
    $stmt->execute([$post_id, $user_email]);
    $row = $stmt->fetch();
    $vote = $row ? $row['vote_type'] : 0;

    return $vote;
}

/**
 * Add or update a vote on a comment
 * 
 * @param int $comment_id Comment ID
 * @param string $user_email User's email
 * @param int $vote_type Vote type (1 for upvote, -1 for downvote)
 * @return array|false New vote count and user's vote or false on failure
 */
function vote_comment($comment_id, $user_email, $vote_type)
{
    global $pdo;

    // Check if user has already voted
    $stmt = $pdo->prepare('SELECT vote_type FROM comment_votes WHERE comment_id = ? AND user_email = ?');
    $stmt->execute([$comment_id, $user_email]);
    $existing_vote = $stmt->fetch();

    if ($existing_vote) {
        // User already voted, check if they're changing their vote
        if ($existing_vote['vote_type'] == $vote_type) {
            // Remove the vote (cancel)
            $stmt = $pdo->prepare('DELETE FROM comment_votes WHERE comment_id = ? AND user_email = ?');
            $stmt->execute([$comment_id, $user_email]);

            $user_vote = 0;
        } else {
            $stmt = $pdo->prepare('UPDATE comment_votes SET vote_type = ?, created_at = CURRENT_TIMESTAMP 
                                 WHERE comment_id = ? AND user_email = ?');
            $stmt->execute([$vote_type, $comment_id, $user_email]);

            $user_vote = $vote_type;
        }
    } else {
        // Add new vote
        $stmt = $pdo->prepare('INSERT INTO comment_votes (comment_id, user_email, vote_type) 
                             VALUES (?, ?, ?)');
        $stmt->execute([$comment_id, $user_email, $vote_type]);

        $user_vote = $vote_type;
    }

    // Update vote count in comments table
    $stmt = $pdo->prepare('SELECT SUM(vote_type) as total_votes FROM comment_votes WHERE comment_id = ?');
    $stmt->execute([$comment_id]);
    $row = $stmt->fetch();

    $total_votes = $row['total_votes'] ?? 0;

    // Update the comment's vote count
    $stmt = $pdo->prepare('UPDATE community_comments SET votes = ? WHERE id = ?');
    $stmt->execute([$total_votes, $comment_id]);

    return [
        'new_vote_count' => $total_votes,
        'user_vote' => $user_vote
    ];
}

/**
 * Get user's vote for a comment
 * 
 * @param int $comment_id Comment ID
 * @param string $user_email User's email
 * @return int Vote type (1, -1) or 0 if no vote
 */
function get_user_comment_vote($comment_id, $user_email)
{
    global $pdo;

    $stmt = $pdo->prepare('SELECT vote_type FROM comment_votes WHERE comment_id = ? AND user_email = ?');
    $stmt->execute([$comment_id, $user_email]);
    $row = $stmt->fetch();
    $vote = $row ? $row['vote_type'] : 0;

    return $vote;
}

