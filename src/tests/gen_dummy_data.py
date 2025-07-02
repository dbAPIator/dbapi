import random
import datetime

def generate_dummy_data(num_users, min_posts_per_user, max_posts_per_user, min_comments_per_user, max_comments_per_user):
    # Open the SQL file for writing
    with open('dummy_data.sql', 'w') as sql_file:
        # Insert users
        for i in range(1, num_users + 1):
            sql_file.write(f"INSERT INTO users (id, name, email, password) VALUES ({i}, 'User {i}', 'user{i}@example.com', 'password{i}');\n")
                
        # Insert posts for each user
        post_id = 1
        for user_id in range(1, num_users + 1):
            num_posts = random.randint(min_posts_per_user, max_posts_per_user)
            for j in range(1, num_posts + 1):
                sql_file.write(f"INSERT INTO posts (id, user_id, title, content, published_at) VALUES ({post_id}, {user_id}, 'Post {j} by User {user_id}', 'Content for Post {j} by User {user_id}', NOW());\n")
                post_id += 1
        maxPostId = post_id
        # Insert comments for each user on random posts
        comment_id = 1
        for user_id in range(1, num_users + 1):
            num_comments = random.randint(min_comments_per_user, max_comments_per_user)
            for _ in range(num_comments):
                post_id = random.randint(1, maxPostId)  # Random post ID
                sql_file.write(f"INSERT IGNORE INTO comments (id, post_id, user_id, content) VALUES ({comment_id}, {post_id}, {user_id}, 'Comment by User {user_id} on Post {post_id}');\n")
                comment_id += 1

# Example usage
generate_dummy_data(100, 35, 60, 50, 100)