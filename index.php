<!DOCTYPE html>
<html lang="en">
<head>
    <?php require_once("parts/head.php")?>
    <title>CodeHub - Forum for Developers</title>
</head>
<body>
    <?php require "parts/header.php" ?>
    
    <main>
        <!-- Hero Section -->
        <section class="bg-dark text-white py-5 text-center">
            <div class="container">
                <h1 class="display-4">CodeHub</h1>
                <p class="lead">A community forum for developers to ask questions, share knowledge, and connect with peers</p>
                <div class="mt-4">
                    <a href="forums.php" class="btn btn-primary btn-lg me-2">Browse Forums</a>
                    <a href="register.php" class="btn btn-outline-light btn-lg">Join Now</a>
                </div>
            </div>
        </section>

        <!-- Featured Categories -->
        <section class="py-5">
            <div class="container">
                <h2 class="mb-4">Popular Categories</h2>
                <div class="row">
                    <div class="col-md-4 mb-4">
                        <div class="card h-100">
                            <div class="card-body">
                                <h5 class="card-title"><i class="fab fa-js-square text-warning me-2"></i>JavaScript</h5>
                                <p class="card-text">Discuss JavaScript frameworks, libraries, and coding techniques.</p>
                                <div class="d-flex justify-content-between align-items-center">
                                    <small class="text-muted">143 topics</small>
                                    <a href="category.php?id=1" class="btn btn-sm btn-outline-primary">View Topics</a>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4 mb-4">
                        <div class="card h-100">
                            <div class="card-body">
                                <h5 class="card-title"><i class="fab fa-php text-info me-2"></i>PHP</h5>
                                <p class="card-text">Get help with PHP programming, frameworks, and backend development.</p>
                                <div class="d-flex justify-content-between align-items-center">
                                    <small class="text-muted">89 topics</small>
                                    <a href="category.php?id=2" class="btn btn-sm btn-outline-primary">View Topics</a>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4 mb-4">
                        <div class="card h-100">
                            <div class="card-body">
                                <h5 class="card-title"><i class="fab fa-python text-success me-2"></i>Python</h5>
                                <p class="card-text">Everything Python - from web development to data science.</p>
                                <div class="d-flex justify-content-between align-items-center">
                                    <small class="text-muted">112 topics</small>
                                    <a href="category.php?id=3" class="btn btn-sm btn-outline-primary">View Topics</a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- Recent Discussions -->
        <section class="py-5 bg-light">
            <div class="container">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h2>Recent Discussions</h2>
                    <a href="topics.php" class="btn btn-sm btn-outline-secondary">View All</a>
                </div>
                <div class="list-group">
                    <a href="topic.php?id=1" class="list-group-item list-group-item-action">
                        <div class="d-flex w-100 justify-content-between">
                            <h5 class="mb-1">How to optimize MySQL queries for large datasets?</h5>
                            <small class="text-muted">3 days ago</small>
                        </div>
                        <p class="mb-1">I'm working with a table containing over 10 million records and my queries are getting slow...</p>
                        <small class="text-muted">Posted by: maria_dev • 15 replies</small>
                    </a>
                    <a href="topic.php?id=2" class="list-group-item list-group-item-action">
                        <div class="d-flex w-100 justify-content-between">
                            <h5 class="mb-1">Best practices for React state management in 2025</h5>
                            <small class="text-muted">1 week ago</small>
                        </div>
                        <p class="mb-1">With so many options like Redux, Context API, and other state management libraries...</p>
                        <small class="text-muted">Posted by: react_master • 23 replies</small>
                    </a>
                    <a href="topic.php?id=3" class="list-group-item list-group-item-action">
                        <div class="d-flex w-100 justify-content-between">
                            <h5 class="mb-1">Implementing JWT authentication in a PHP application</h5>
                            <small class="text-muted">2 weeks ago</small>
                        </div>
                        <p class="mb-1">I'm building a REST API with PHP and need a secure way to handle authentication...</p>
                        <small class="text-muted">Posted by: php_developer • 8 replies</small>
                    </a>
                </div>
            </div>
        </section>

        <!-- Community Stats -->
        <section class="py-5">
            <div class="container">
                <h2 class="text-center mb-5">Community Statistics</h2>
                <div class="row text-center">
                    <div class="col-md-3 mb-4">
                        <div class="border rounded p-4">
                            <h3 class="display-4 fw-bold text-primary">1,250</h3>
                            <p class="text-muted mb-0">Members</p>
                        </div>
                    </div>
                    <div class="col-md-3 mb-4">
                        <div class="border rounded p-4">
                            <h3 class="display-4 fw-bold text-success">568</h3>
                            <p class="text-muted mb-0">Topics</p>
                        </div>
                    </div>
                    <div class="col-md-3 mb-4">
                        <div class="border rounded p-4">
                            <h3 class="display-4 fw-bold text-info">4,721</h3>
                            <p class="text-muted mb-0">Posts</p>
                        </div>
                    </div>
                    <div class="col-md-3 mb-4">
                        <div class="border rounded p-4">
                            <h3 class="display-4 fw-bold text-warning">15</h3>
                            <p class="text-muted mb-0">Categories</p>
                        </div>
                    </div>
                </div>
            </div>
        </section>
    </main>

    <?php require "parts/footer.php" ?>
</body>
</html>