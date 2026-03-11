<?php
include 'data.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Modern Dashboard</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <div class="dashboard">
        <div class="sidebar-menu">
            <div class="sidebar-top">
                <div class="logo">kpi</div>
                <button class="nav-pill active">Overview</button>
                <button class="nav-pill">Insights</button>
                <button class="nav-pill">Analytics</button>
                <button class="nav-pill">Audiences</button>
                <button class="nav-pill">Reports</button>
            </div>

            <div class="sidebar-bottom">
                <button class="icon-btn">L</button>
                <div class="avatar-main">P</div>
            </div>
        </div>

        <div class="main-grid">
            <div class="left-panel">
                <div class="hero-row">
                    <div class="welcome-card">
                        <p class="small">Welcome back,</p>
                        <h1>Darlene<br>Robertson</h1>
                        <span class="badge">Premium</span>
                    </div>

                    <div class="stats-grid">
                        <?php foreach ($cards as $card): ?>
                            <div class="card <?php echo $card['highlight'] ? 'highlight' : ''; ?>">
                                <div class="expand-btn">↗</div>
                                <p class="mini-title"><?php echo $card['title']; ?></p>
                                <div class="value"><?php echo $card['value']; ?></div>
                                <p class="change"><?php echo $card['change']; ?></p>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div class="tabs-row">
                    <div class="tab-group">
                        <button class="filter-pill active">All</button>
                        <button class="filter-pill">Engagement</button>
                        <button class="filter-pill">Visit</button>
                        <button class="filter-pill">Post</button>
                    </div>

                    <div class="action-group">
                        <button class="icon-btn">⏷</button>
                        <button class="icon-btn">🗓</button>
                        <button class="download-btn">Download reports</button>
                    </div>
                </div>

                <div class="bottom-grid">
                    <div class="chart-card">
                        <div class="chart-header">
                            <div class="chart-title-wrap">
                                <div class="chart-title">Engagement rate</div>
                                <div class="segmented">
                                    <button>Monthly</button>
                                    <button class="active">Annually</button>
                                </div>
                            </div>
                            <button class="small-round-btn">•••</button>
                        </div>

                        <div class="bar-chart">
                            <?php foreach ($barData as $index => $value): ?>
                                <div class="bar-wrap">
                                    <div class="bar <?php echo $index === 3 ? 'active' : ''; ?>" style="height: <?php echo $value * 2; ?>px;">
                                        <?php if ($index === 3): ?>
                                            <div class="bar-tooltip">
                                                <span>April, 2025</span><br>
                                                <strong>379,502</strong>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                    <div class="bar-label"><?php echo $months[$index]; ?></div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <div class="chart-card">
                        <div class="chart-header">
                            <div class="chart-title-wrap">
                                <div class="chart-title">Time visit</div>
                                <div class="sub-pill">Follower ⏷</div>
                            </div>
                        </div>

                        <div class="dot-legend">
                            <span><span class="dot one"></span>&lt;500</span>
                            <span><span class="dot two"></span>&gt;1,000</span>
                            <span><span class="dot three"></span>&gt;2,000</span>
                            <span><span class="dot two"></span>&gt;3,000</span>
                        </div>

                        <div class="heatmap-wrap">
                            <table class="heatmap-table">
                                <thead>
                                    <tr>
                                        <th></th>
                                        <?php foreach ($heatmapCols as $col): ?>
                                            <th><?php echo $col; ?></th>
                                        <?php endforeach; ?>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($heatmapRows as $time => $row): ?>
                                        <tr>
                                            <td class="label"><?php echo $time; ?></td>
                                            <?php foreach ($row as $cell): ?>
                                                <td><div class="heat-cell lv<?php echo $cell; ?>"></div></td>
                                            <?php endforeach; ?>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <div class="right-panel">
                <div class="message-card">
                    <div class="chart-header">
                        <div class="chart-title">Messages</div>
                        <button class="small-round-btn">+</button>
                    </div>

                    <div class="search-box">Search message</div>

                    <div class="message-list">
                        <?php foreach ($messages as $index => $msg): ?>
                            <div class="message-item">
                                <div class="avatar a<?php echo ($index % 3) + 1; ?>">
                                    <?php echo $msg['avatar']; ?>
                                </div>
                                <div class="message-content">
                                    <div class="message-top">
                                        <span class="message-name"><?php echo $msg['name']; ?></span>
                                        <span class="message-time"><?php echo $msg['time']; ?></span>
                                    </div>
                                    <p class="message-text"><?php echo $msg['text']; ?></p>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="script.js"></script>
</body>
</html>