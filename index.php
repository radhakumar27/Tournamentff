<?php
require_once 'common/header.php';

$message = '';
$message_type = '';

// --- Handle Join Tournament ---
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['join_tournament'])) {
    $tournament_id = $_POST['tournament_id'];
    $entry_fee = $_POST['entry_fee'];

    // Check if user has enough balance
    $stmt_balance = $conn->prepare("SELECT wallet_balance FROM users WHERE id = ?");
    $stmt_balance->bind_param("i", $_SESSION['user_id']);
    $stmt_balance->execute();
    $user_result = $stmt_balance->get_result()->fetch_assoc();
    $current_balance = $user_result['wallet_balance'];
    $stmt_balance->close();

    if ($current_balance >= $entry_fee) {
        // Check if user has already joined
        $stmt_check = $conn->prepare("SELECT id FROM participants WHERE user_id = ? AND tournament_id = ?");
        $stmt_check->bind_param("ii", $_SESSION['user_id'], $tournament_id);
        $stmt_check->execute();
        $stmt_check->store_result();

        if ($stmt_check->num_rows > 0) {
            $message = "You have already joined this tournament.";
            $message_type = "error";
        } else {
            // Deduct entry fee and add to participants
            $conn->begin_transaction();
            try {
                // Deduct from wallet
                $new_balance = $current_balance - $entry_fee;
                $stmt_update = $conn->prepare("UPDATE users SET wallet_balance = ? WHERE id = ?");
                $stmt_update->bind_param("di", $new_balance, $_SESSION['user_id']);
                $stmt_update->execute();
                $stmt_update->close();

                // Record transaction
                $desc = "Entry fee for tournament #" . $tournament_id;
                $stmt_trans = $conn->prepare("INSERT INTO transactions (user_id, amount, type, description) VALUES (?, ?, 'debit', ?)");
                $stmt_trans->bind_param("ids", $_SESSION['user_id'], $entry_fee, $desc);
                $stmt_trans->execute();
                $stmt_trans->close();
                
                // Add to participants
                $stmt_join = $conn->prepare("INSERT INTO participants (user_id, tournament_id) VALUES (?, ?)");
                $stmt_join->bind_param("ii", $_SESSION['user_id'], $tournament_id);
                $stmt_join->execute();
                $stmt_join->close();

                $conn->commit();
                $message = "Successfully joined the tournament!";
                $message_type = "success";

                // Refresh wallet balance in header
                $wallet_balance = $new_balance;

            } catch (mysqli_sql_exception $exception) {
                $conn->rollback();
                $message = "An error occurred. Please try again.";
                $message_type = "error";
            }
        }
        $stmt_check->close();
    } else {
        $message = "Insufficient balance to join.";
        $message_type = "error";
    }
}

// Fetch Upcoming Tournaments
$tournaments_result = $conn->query("SELECT * FROM tournaments WHERE status = 'Upcoming' ORDER BY match_time ASC");
?>

<div class="space-y-6">
    <h2 class="text-2xl font-bold text-gray-100">Upcoming Tournaments</h2>

    <?php if ($message): ?>
        <div class="p-4 rounded-lg <?php echo $message_type === 'success' ? 'bg-green-500' : 'bg-red-500'; ?> text-white">
            <?php echo htmlspecialchars($message); ?>
        </div>
    <?php endif; ?>

    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
        <?php if ($tournaments_result->num_rows > 0): ?>
            <?php while($row = $tournaments_result->fetch_assoc()): ?>
                <div class="bg-gray-800 rounded-lg shadow-lg overflow-hidden">
                    <div class="p-6">
                        <h3 class="text-xl font-bold text-teal-400 mb-2"><?php echo htmlspecialchars($row['title']); ?></h3>
                        <p class="text-gray-400 font-semibold mb-4"><?php echo htmlspecialchars($row['game_name']); ?></p>
                        
                        <div class="grid grid-cols-2 gap-4 text-sm mb-4">
                            <div>
                                <p class="text-gray-500">Prize Pool</p>
                                <p class="font-bold text-lg text-white">₹<?php echo number_format($row['prize_pool']); ?></p>
                            </div>
                            <div>
                                <p class="text-gray-500">Entry Fee</p>
                                <p class="font-bold text-lg text-white">₹<?php echo number_format($row['entry_fee']); ?></p>
                            </div>
                        </div>

                        <div class="text-sm text-gray-400 mb-4">
                            <i class="fas fa-clock mr-2"></i>
                            <span><?php echo date('d M Y, h:i A', strtotime($row['match_time'])); ?></span>
                        </div>

                        <form action="index.php" method="POST">
                            <input type="hidden" name="tournament_id" value="<?php echo $row['id']; ?>">
                            <input type="hidden" name="entry_fee" value="<?php echo $row['entry_fee']; ?>">
                            <button type="submit" name="join_tournament" class="w-full bg-teal-500 hover:bg-teal-600 text-white font-bold py-2 px-4 rounded-md transition-colors">
                                Join Now
                            </button>
                        </form>
                    </div>
                </div>
            <?php endwhile; ?>
        <?php else: ?>
            <p class="text-gray-400 col-span-full">No upcoming tournaments at the moment.</p>
        <?php endif; ?>
    </div>
</div>

<?php require_once 'common/bottom.php'; ?>