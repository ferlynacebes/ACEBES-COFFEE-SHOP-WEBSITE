<?php

session_start();

require_once __DIR__ . "/../config/db.php";

if (
    !isset($_SESSION["user_id"]) ||
    ($_SESSION["user_role"] ?? "") !== "admin"
) {
    header("Location: ../login.php");
    exit;
}

$adminName = $_SESSION["user_name"] ?? "Administrator";
$search = trim($_GET["search"] ?? "");
$alert = "";
$error = "";

function e($value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES, "UTF-8");
}

/*
|--------------------------------------------------------------------------
| DELETE MESSAGE
|--------------------------------------------------------------------------
*/
if (
    $_SERVER["REQUEST_METHOD"] === "POST" &&
    ($_POST["action"] ?? "") === "delete"
) {
    $messageId = (int)($_POST["message_id"] ?? 0);

    if ($messageId > 0) {
        $stmt = $conn->prepare(
            "DELETE FROM contact_messages WHERE id = ?"
        );

        if ($stmt) {
            $stmt->bind_param("i", $messageId);

            if ($stmt->execute()) {
                $alert = "Message deleted successfully.";
            } else {
                $error = "Unable to delete the message.";
            }

            $stmt->close();
        } else {
            $error = "Unable to process the request.";
        }
    }
}

/*
|--------------------------------------------------------------------------
| LOAD MESSAGES
|--------------------------------------------------------------------------
*/
$messages = [];

if ($search !== "") {

    $term = "%" . $search . "%";

    $stmt = $conn->prepare("
        SELECT
            id,
            name,
            email,
            subject,
            message,
            created_at
        FROM contact_messages
        WHERE
            name LIKE ?
            OR email LIKE ?
            OR subject LIKE ?
            OR message LIKE ?
        ORDER BY created_at DESC, id DESC
    ");

    if ($stmt) {
        $stmt->bind_param(
            "ssss",
            $term,
            $term,
            $term,
            $term
        );

        if ($stmt->execute()) {
            $result = $stmt->get_result();

            while ($row = $result->fetch_assoc()) {
                $messages[] = $row;
            }

            $result->free();
        } else {
            $error = "Unable to load customer messages.";
        }

        $stmt->close();
    } else {
        $error = "Unable to load customer messages.";
    }

} else {

    $result = $conn->query("
        SELECT
            id,
            name,
            email,
            subject,
            message,
            created_at
        FROM contact_messages
        ORDER BY created_at DESC, id DESC
    ");

    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $messages[] = $row;
        }

        $result->free();
    } else {
        $error = "Unable to load customer messages.";
    }
}

/*
|--------------------------------------------------------------------------
| COUNTERS
|--------------------------------------------------------------------------
*/
$totalMessages = 0;
$todayMessages = 0;

$result = $conn->query(
    "SELECT COUNT(*) AS total FROM contact_messages"
);

if ($result) {
    $totalMessages = (int)($result->fetch_assoc()["total"] ?? 0);
    $result->free();
}

$result = $conn->query("
    SELECT COUNT(*) AS total
    FROM contact_messages
    WHERE DATE(created_at) = CURDATE()
");

if ($result) {
    $todayMessages = (int)($result->fetch_assoc()["total"] ?? 0);
    $result->free();
}

?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>Messages | Acebes Coffee Admin</title>

    <link
        rel="preconnect"
        href="https://fonts.googleapis.com"
    >

    <link
        rel="preconnect"
        href="https://fonts.gstatic.com"
        crossorigin
    >

    <link
        href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700;800&display=swap"
        rel="stylesheet"
    >

    <style>

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            min-height: 100vh;
            background: #f6f1eb;
            color: #2a1a13;
            font-family: "Montserrat", Arial, sans-serif;
        }

        .main {
            margin-left: 250px;
            width: calc(100% - 250px);
            min-height: 100vh;
            padding: 35px;
        }

        .page-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 20px;
            margin-bottom: 26px;
        }

        .eyebrow {
            color: #b27e4d;
            font-size: 10px;
            font-weight: 800;
            letter-spacing: 2px;
            text-transform: uppercase;
            margin-bottom: 8px;
        }

        .page-header h1 {
            font-size: 32px;
            font-weight: 800;
        }

        .page-header p {
            margin-top: 7px;
            color: #806f64;
            font-size: 14px;
        }

        .welcome {
            padding: 12px 16px;
            border: 1px solid #e8ddd4;
            border-radius: 12px;
            background: #fff;
            color: #806f64;
            font-size: 12px;
            font-weight: 600;
        }

        .welcome strong {
            color: #2a1a13;
        }

        .stats {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 18px;
            margin-bottom: 22px;
        }

        .stat-card {
            padding: 22px;
            background: #fff;
            border: 1px solid #e8ddd4;
            border-radius: 16px;
            box-shadow: 0 8px 24px rgba(70,45,30,.05);
        }

        .stat-label {
            color: #806f64;
            font-size: 12px;
            font-weight: 600;
        }

        .stat-value {
            margin-top: 8px;
            font-size: 28px;
            font-weight: 800;
        }

        .content-card {
            overflow: hidden;
            background: #fff;
            border: 1px solid #e8ddd4;
            border-radius: 16px;
            box-shadow: 0 8px 24px rgba(70,45,30,.05);
        }

        .card-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 20px;
            padding: 22px 24px;
            border-bottom: 1px solid #eee3db;
        }

        .card-header h2 {
            font-size: 17px;
            font-weight: 800;
        }

        .card-header p {
            margin-top: 5px;
            color: #9a887c;
            font-size: 12px;
        }

        .search-form {
            display: flex;
            gap: 8px;
            width: min(430px, 100%);
        }

        .search-form input {
            min-width: 0;
            flex: 1;
            height: 40px;
            padding: 0 13px;
            border: 1px solid #dfd0c4;
            border-radius: 9px;
            outline: none;
            background: #fffdfb;
            color: #2a1a13;
            font-size: 12px;
        }

        .search-form button {
            height: 40px;
            padding: 0 16px;
            border: 0;
            border-radius: 9px;
            background: #4b2e20;
            color: #fff;
            cursor: pointer;
            font-size: 12px;
            font-weight: 700;
        }

        .alert {
            margin: 18px 24px 0;
            padding: 12px 14px;
            border-radius: 9px;
            font-size: 12px;
            font-weight: 600;
        }

        .alert.success {
            background: #eaf5ed;
            color: #3d704b;
        }

        .alert.error {
            background: #faeaea;
            color: #9a3d3d;
        }

        .table-wrap {
            overflow-x: auto;
        }

        table {
            width: 100%;
            min-width: 950px;
            border-collapse: collapse;
        }

        th {
            padding: 13px 20px;
            text-align: left;
            background: #fbf8f5;
            border-bottom: 1px solid #eee3db;
            color: #907e72;
            font-size: 10px;
            font-weight: 800;
            letter-spacing: .7px;
            text-transform: uppercase;
        }

        td {
            padding: 18px 20px;
            vertical-align: top;
            border-bottom: 1px solid #f0e7e0;
            font-size: 12px;
        }

        tbody tr:hover {
            background: #fdfaf7;
        }

        .sender-name {
            color: #2a1a13;
            font-size: 13px;
            font-weight: 800;
        }

        .sender-email {
            margin-top: 5px;
            color: #907e72;
            font-size: 11px;
        }

        .subject {
            color: #4b2e20;
            font-weight: 700;
        }

        .message-text {
            max-width: 390px;
            color: #6f6057;
            line-height: 1.6;
            white-space: pre-wrap;
            word-break: break-word;
        }

        .date {
            color: #907e72;
            font-size: 11px;
            white-space: nowrap;
        }

        .delete-btn {
            border: 0;
            border-radius: 8px;
            padding: 9px 12px;
            background: #f8e9e5;
            color: #984d3e;
            cursor: pointer;
            font-size: 11px;
            font-weight: 700;
        }

        .delete-btn:hover {
            background: #f3dcd5;
        }

        .empty {
            padding: 65px 20px;
            text-align: center;
            color: #907e72;
        }

        .empty-icon {
            display: block;
            margin-bottom: 12px;
            font-size: 34px;
        }

        .empty strong {
            display: block;
            margin-bottom: 6px;
            color: #2a1a13;
            font-size: 15px;
        }

        @media (max-width: 950px) {

            .main {
                margin-left: 220px;
                width: calc(100% - 220px);
                padding: 25px;
            }

            .page-header {
                align-items: flex-start;
                flex-direction: column;
            }

            .search-form {
                width: 100%;
                max-width: 500px;
            }
        }

        @media (max-width: 700px) {

            .main {
                margin-left: 0;
                width: 100%;
                padding: 20px;
            }

            .stats {
                grid-template-columns: 1fr;
            }

            .card-header {
                align-items: flex-start;
                flex-direction: column;
            }
        }

    </style>

</head>

<body>

<div class="admin-layout">

    <?php include __DIR__ . "/sidebar.php"; ?>

    <main class="main">

        <header class="page-header">

            <div>

                <div class="eyebrow">
                    Acebes Coffee Administration
                </div>

                <h1>Customer Messages</h1>

                <p>
                    View and manage messages submitted through the Contact Us form.
                </p>

            </div>

            <div class="welcome">
                Welcome,
                <strong><?= e($adminName) ?></strong>
            </div>

        </header>


        <section class="stats">

            <div class="stat-card">
                <div class="stat-label">Total Messages</div>
                <div class="stat-value"><?= $totalMessages ?></div>
            </div>

            <div class="stat-card">
                <div class="stat-label">Messages Today</div>
                <div class="stat-value"><?= $todayMessages ?></div>
            </div>

        </section>


        <section class="content-card">

            <div class="card-header">

                <div>
                    <h2>Inbox</h2>
                    <p>Customer inquiries and feedback</p>
                </div>

                <form
                    method="GET"
                    action="messages.php"
                    class="search-form"
                >

                    <input
                        type="search"
                        name="search"
                        value="<?= e($search) ?>"
                        placeholder="Search messages..."
                    >

                    <button type="submit">
                        Search
                    </button>

                </form>

            </div>


            <?php if ($alert !== ""): ?>
                <div class="alert success">
                    <?= e($alert) ?>
                </div>
            <?php endif; ?>


            <?php if ($error !== ""): ?>
                <div class="alert error">
                    <?= e($error) ?>
                </div>
            <?php endif; ?>


            <?php if (empty($messages)): ?>

                <div class="empty">

                    <span class="empty-icon">✉️</span>

                    <strong>No messages found</strong>

                    <?php if ($search !== ""): ?>
                        <span>Try another search term.</span>
                    <?php else: ?>
                        <span>
                            Messages from the Contact Us form will appear here.
                        </span>
                    <?php endif; ?>

                </div>

            <?php else: ?>

                <div class="table-wrap">

                    <table>

                        <thead>
                            <tr>
                                <th>Sender</th>
                                <th>Subject</th>
                                <th>Message</th>
                                <th>Date</th>
                                <th>Action</th>
                            </tr>
                        </thead>

                        <tbody>

                        <?php foreach ($messages as $item): ?>

                            <tr>

                                <td>
                                    <div class="sender-name">
                                        <?= e($item["name"]) ?>
                                    </div>

                                    <div class="sender-email">
                                        <?= e($item["email"]) ?>
                                    </div>
                                </td>

                                <td>
                                    <div class="subject">
                                        <?= e($item["subject"]) ?>
                                    </div>
                                </td>

                                <td>
                                    <div class="message-text">
                                        <?= e($item["message"]) ?>
                                    </div>
                                </td>

                                <td>
                                    <div class="date">
                                        <?= e(
                                            date(
                                                "M d, Y h:i A",
                                                strtotime($item["created_at"])
                                            )
                                        ) ?>
                                    </div>
                                </td>

                                <td>

                                    <form
                                        method="POST"
                                        action="messages.php"
                                        onsubmit="return confirm('Delete this message?');"
                                    >

                                        <input
                                            type="hidden"
                                            name="action"
                                            value="delete"
                                        >

                                        <input
                                            type="hidden"
                                            name="message_id"
                                            value="<?= (int)$item["id"] ?>"
                                        >

                                        <button
                                            type="submit"
                                            class="delete-btn"
                                        >
                                            Delete
                                        </button>

                                    </form>

                                </td>

                            </tr>

                        <?php endforeach; ?>

                        </tbody>

                    </table>

                </div>

            <?php endif; ?>

        </section>

    </main>

</div>

</body>
</html>
