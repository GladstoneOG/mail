<?php
/**
 * View Message Page - with retract support and HTML body rendering
 */
$userId = auth_user_id();
$msgId = isset($_GET['id']) ? intval($_GET['id']) : 0;
if (!$msgId) redirect('index.php?page=inbox');

$msg = db_fetch_one($conn,
    "SELECT m.*, u.display_name AS sender_name, u.username AS sender_username
     FROM mail_messages m JOIN mail_users u ON m.sender_id = u.id WHERE m.id = ?",
    array($msgId));
if (!$msg) redirect('index.php?page=inbox');

// Check access
$recipRow = db_fetch_one($conn,
    "SELECT id, is_starred, is_read FROM mail_recipients WHERE message_id = ? AND recipient_id = ?",
    array($msgId, $userId));
$isSender = (intval($msg['sender_id']) === $userId);
if (!$recipRow && !$isSender) redirect('index.php?page=inbox');

// Check if retracted
$isRetracted = isset($msg['is_retracted']) && $msg['is_retracted'];

// Mark as read + auto-cleanup attachments
if ($recipRow && !$recipRow['is_read']) {
    db_execute($conn, "UPDATE mail_recipients SET is_read = 1, read_at = GETDATE() WHERE message_id = ? AND recipient_id = ?",
               array($msgId, $userId));
    // Check if ALL recipients have now read — if so, delete physical attachment files
    // (Suspended to allow previewing attachments indefinitely)
    /*
    $unreadLeft = intval(db_fetch_scalar($conn,
        "SELECT COUNT(*) FROM mail_recipients WHERE message_id = ? AND is_read = 0", array($msgId)));
    if ($unreadLeft === 0 && $msg['has_attachments']) {
        $atts = db_fetch_all($conn, "SELECT stored_name FROM mail_attachments WHERE message_id = ?", array($msgId));
        foreach ($atts as $a) {
            $path = UPLOAD_DIR . $a['stored_name'];
            if (file_exists($path)) @unlink($path);
        }
    }
    */
}

// Get recipients
$recipients = db_fetch_all($conn,
    "SELECT u.display_name, u.username, mr.recipient_type
     FROM mail_recipients mr JOIN mail_users u ON mr.recipient_id = u.id
     WHERE mr.message_id = ? ORDER BY mr.recipient_type", array($msgId));
$toList = array(); $ccList = array();
foreach ($recipients as $r) {
    if ($r['recipient_type'] === 'cc') $ccList[] = $r;
    elseif ($r['recipient_type'] !== 'bcc') $toList[] = $r;
}

$attachments = db_fetch_all($conn, "SELECT * FROM mail_attachments WHERE message_id = ?", array($msgId));
$starred = $recipRow ? $recipRow['is_starred'] : false;
$backPage = isset($_GET['from']) ? $_GET['from'] : 'inbox';

// --- Conversation Thread Building ---
// 1. Walk up the reply chain from current message to find the root message of the thread
$rootId = $msgId;
$currMsg = $msg;
$visited = array($msgId => true); // prevent loops

while ($currMsg && !empty($currMsg['reply_to_id'])) {
    $parentId = intval($currMsg['reply_to_id']);
    if (isset($visited[$parentId])) {
        break; // Cycle detected
    }
    // Fetch parent message
    $parentMsg = db_fetch_one($conn, "SELECT id, reply_to_id FROM mail_messages WHERE id = ?", array($parentId));
    if ($parentMsg) {
        $rootId = $parentId;
        $currMsg = $parentMsg;
        $visited[$parentId] = true;
    } else {
        break;
    }
}

// 2. Traverse down from the root to collect all descendants of the thread
$threadMsgIds = array($rootId);
$queue = array($rootId);
$visitedDescendants = array($rootId => true);

while (!empty($queue)) {
    $parentId = array_shift($queue);
    // Find all immediate replies to this message
    $replies = db_fetch_all($conn, "SELECT id FROM mail_messages WHERE reply_to_id = ?", array($parentId));
    foreach ($replies as $r) {
        $replyId = intval($r['id']);
        if (!isset($visitedDescendants[$replyId])) {
            $visitedDescendants[$replyId] = true;
            $threadMsgIds[] = $replyId;
            $queue[] = $replyId;
        }
    }
}

// 3. Fetch full info for each message in the thread that the user has access to
$threadMessages = array();
foreach ($threadMsgIds as $tid) {
    $tmsg = db_fetch_one($conn,
        "SELECT m.*, u.display_name AS sender_name, u.username AS sender_username
         FROM mail_messages m JOIN mail_users u ON m.sender_id = u.id WHERE m.id = ?",
        array($tid));
    if (!$tmsg) continue;
    
    // User has access if they are the sender, or they are a recipient and it is not deleted
    $trecip = db_fetch_one($conn,
        "SELECT id, is_read, is_starred, is_deleted FROM mail_recipients WHERE message_id = ? AND recipient_id = ?",
        array($tid, $userId));
    $tisSender = (intval($tmsg['sender_id']) === $userId);
    
    if (!$tisSender && (!$trecip || $trecip['is_deleted'])) {
        continue; // Hide messages user doesn't have access to
    }
    
    // Get recipients list
    $trecipients = db_fetch_all($conn,
        "SELECT u.display_name, u.username, mr.recipient_type
         FROM mail_recipients mr JOIN mail_users u ON mr.recipient_id = u.id
         WHERE mr.message_id = ? ORDER BY mr.recipient_type", array($tid));
    
    $tToList = array(); $tCcList = array();
    foreach ($trecipients as $tr) {
        if ($tr['recipient_type'] === 'cc') $tCcList[] = $tr;
        elseif ($tr['recipient_type'] !== 'bcc') $tToList[] = $tr;
    }
    
    $tatts = db_fetch_all($conn, "SELECT * FROM mail_attachments WHERE message_id = ?", array($tid));
    
    // Check if the recipient has read this message; if not and user is recipient, mark it read
    if ($trecip && !$trecip['is_read']) {
        db_execute($conn, "UPDATE mail_recipients SET is_read = 1, read_at = GETDATE() WHERE message_id = ? AND recipient_id = ?",
                   array($tid, $userId));
        $trecip['is_read'] = 1;
    }
    
    $threadMessages[] = array(
        'msg' => $tmsg,
        'is_sender' => $tisSender,
        'recip_row' => $trecip,
        'to_list' => $tToList,
        'cc_list' => $tCcList,
        'attachments' => $tatts
    );
}

// 4. Sort the thread chronologically (oldest first)
usort($threadMessages, function($a, $b) {
    $timeA = $a['msg']['sent_at'] ? $a['msg']['sent_at'] : $a['msg']['created_at'];
    $timeB = $b['msg']['sent_at'] ? $b['msg']['sent_at'] : $b['msg']['created_at'];
    $tsA = is_a($timeA, 'DateTime') ? $timeA->getTimestamp() : strtotime($timeA);
    $tsB = is_a($timeB, 'DateTime') ? $timeB->getTimestamp() : strtotime($timeB);
    return $tsA - $tsB;
});

// Helper function to recursively fetch forwarded message details
function get_forwarded_message_chain($conn, $forwardedId, $userId) {
    $orig = db_fetch_one($conn,
        "SELECT m.*, u.display_name AS sender_name, u.username AS sender_username
         FROM mail_messages m JOIN mail_users u ON m.sender_id = u.id WHERE m.id = ?",
        array($forwardedId));
    if (!$orig) return null;
    
    $recipients = db_fetch_all($conn,
        "SELECT u.display_name, u.username, mr.recipient_type
         FROM mail_recipients mr JOIN mail_users u ON mr.recipient_id = u.id
         WHERE mr.message_id = ? ORDER BY mr.recipient_type", array($forwardedId));
    
    $toList = array(); $ccList = array();
    foreach ($recipients as $r) {
        if ($r['recipient_type'] === 'cc') $ccList[] = $r;
        elseif ($r['recipient_type'] !== 'bcc') $toList[] = $r;
    }
    
    $atts = db_fetch_all($conn, "SELECT * FROM mail_attachments WHERE message_id = ?", array($forwardedId));
    
    return array(
        'msg' => $orig,
        'to_list' => $toList,
        'cc_list' => $ccList,
        'attachments' => $atts,
        'forwarded_from' => !empty($orig['forwarded_from_id']) ? get_forwarded_message_chain($conn, $orig['forwarded_from_id'], $userId) : null
    );
}

// Function to render the forwarded chain recursively with styled containers
function render_forwarded_chain_html($fwd) {
    if (!$fwd) return;
    $msg = $fwd['msg'];
    $toList = $fwd['to_list'];
    $ccList = $fwd['cc_list'];
    $atts = $fwd['attachments'];
    $isRetracted = isset($msg['is_retracted']) && $msg['is_retracted'];
    ?>
    <div class="forwarded-message-container">
        <div class="forwarded-message-header">
            <span class="forwarded-label">&#x21AA; Forwarded Message</span>
            <div style="margin-top: 6px; font-size: 13px; line-height: 1.4;">
                <strong>From:</strong> <?php echo e($msg['sender_name']); ?> (@<?php echo e($msg['sender_username']); ?>)<br>
                <strong>Date:</strong> <?php echo format_date($msg['sent_at'] ? $msg['sent_at'] : $msg['created_at']); ?><br>
                <strong>Subject:</strong> <?php echo e($msg['subject']); ?><br>
                <strong>To:</strong> <?php foreach ($toList as $i => $r): ?><?php echo ($i > 0 ? ', ' : '') . e($r['display_name']); ?><?php endforeach; ?>
                <?php if (!empty($ccList)): ?>
                    <br><strong>CC:</strong> <?php foreach ($ccList as $i => $r): ?><?php echo ($i > 0 ? ', ' : '') . e($r['display_name']); ?><?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
        
        <?php if ($isRetracted): ?>
            <div class="alert alert-error" style="padding:10px; font-size:12px; margin-top:10px;">
                &#x26A0; This forwarded message was retracted by the sender.
            </div>
        <?php else: ?>
            <?php if (!empty($atts)): ?>
                <div class="forwarded-attachments" style="margin-top: 10px; padding-top: 10px; border-top: 1px dashed var(--border);">
                    <span style="font-size: 12px; font-weight: 600; color: var(--text2);">&#x1F4CE; Forwarded Attachments:</span>
                    <div class="attachment-list" style="margin-top: 5px; gap: 6px;">
                        <?php foreach ($atts as $att):
                            $decayInfo = get_attachment_decay_info($att['created_at']);
                            $isExpired = $decayInfo['expired'];
                            $fileExists = $isExpired ? false : file_exists(UPLOAD_DIR . $att['stored_name']);
                        ?>
                            <?php if ($fileExists):
                                $ext = strtolower(pathinfo($att['original_name'], PATHINFO_EXTENSION));
                                $isPreviewable = in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp', 'pdf', 'txt']);
                                $previewUrl = "api/attachments.php?action=preview&id=" . $att['id'];
                                $downloadUrl = "api/attachments.php?action=download&id=" . $att['id'];
                            ?>
                                <div class="attachment-item" style="padding:0; overflow:hidden; display:flex; align-items:stretch; font-size:11px; height:28px;">
                                    <a href="<?php echo $isPreviewable ? 'javascript:void(0)' : $downloadUrl; ?>" 
                                       <?php if($isPreviewable) echo 'onclick="openPreview(\''.$previewUrl.'\', \''.e(addslashes($att['original_name'])).'\', \''.$ext.'\')"'; ?>
                                       style="display:flex; align-items:center; gap:6px; padding:4px 8px; flex:1; text-decoration:none; color:var(--text);">
                                        <span class="att-icon">&#x1F4C4;</span>
                                        <span class="att-name"><?php echo e($att['original_name']); ?></span>
                                        <span class="att-size" style="color:var(--text3);"><?php echo format_size($att['file_size']); ?></span>
                                    </a>
                                    <a href="<?php echo $downloadUrl; ?>" title="Download" style="display:flex; align-items:center; justify-content:center; padding:0 8px; border-left:1px solid var(--border); text-decoration:none; color:var(--text2);">
                                        &#x1F4E5;
                                    </a>
                                </div>
                            <?php else: ?>
                                <span class="attachment-item att-removed" style="font-size:11px; padding:4px 8px; opacity:0.6;">
                                    <span class="att-icon">&#x1F6AB;</span>
                                    <span><?php echo e($att['original_name']); ?> (removed)</span>
                                </span>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>
            
            <div class="forwarded-message-body" style="margin-top:12px; font-size:14px; color:var(--text);">
                <?php echo sanitize_html($msg['body']); ?>
            </div>
            
            <?php if (!empty($msg['forwarded_from_id'])):
                $nextFwd = get_forwarded_message_chain($conn, $msg['forwarded_from_id'], $userId);
                if ($nextFwd):
                    render_forwarded_chain_html($nextFwd);
                endif;
            endif; ?>
        <?php endif; ?>
    </div>
    <?php
}

// Find the latest message in the thread
$lastThreadMsg = end($threadMessages);
reset($threadMessages);
$lastMsgId = $lastThreadMsg ? $lastThreadMsg['msg']['id'] : $msgId;
?>

<div class="page-header">
    <a href="index.php?page=<?php echo e($backPage); ?>" class="back-btn">&larr; Back</a>
</div>

<h2 class="thread-subject"><?php echo e($msg['subject']); ?></h2>

<div class="thread-container">
    <?php foreach ($threadMessages as $index => $tdata): 
        $tmsg = $tdata['msg'];
        $tId = $tmsg['id'];
        $tisSender = $tdata['is_sender'];
        $trecip = $tdata['recip_row'];
        $tToList = $tdata['to_list'];
        $tCcList = $tdata['cc_list'];
        $tatts = $tdata['attachments'];
        $tisRetracted = isset($tmsg['is_retracted']) && $tmsg['is_retracted'];
        
        // Expand the specific message they clicked on, OR the last message in the thread
        $isExpanded = ($tId === $msgId || (count($threadMessages) === 1) || ($tId === $lastMsgId && $msgId === 0));
    ?>
        <div class="thread-card <?php echo $isExpanded ? 'expanded' : 'collapsed'; ?>" id="thread-card-<?php echo $tId; ?>" onclick="toggleThreadCard(<?php echo $tId; ?>, event)">
            <!-- Collapsed Header -->
            <div class="thread-card-collapsed-header">
                <div class="thread-card-avatar" style="background:<?php echo get_avatar_color($tmsg['sender_name']); ?>">
                    <?php echo e(get_initials($tmsg['sender_name'])); ?>
                </div>
                <div class="thread-card-collapsed-info">
                    <span class="thread-card-collapsed-sender"><?php echo e($tmsg['sender_name']); ?></span>
                    <span class="thread-card-collapsed-snippet">
                        <?php if ($tisRetracted): ?>
                            <span style="font-style: italic; opacity: 0.6;">This message was retracted by the sender.</span>
                        <?php else: ?>
                            <?php echo e(truncate_text(strip_tags($tmsg['body']), 90)); ?>
                        <?php endif; ?>
                    </span>
                </div>
                <div class="thread-card-collapsed-meta">
                    <span class="thread-card-collapsed-date"><?php echo format_date($tmsg['sent_at'] ? $tmsg['sent_at'] : $tmsg['created_at']); ?></span>
                    <span class="thread-card-expand-icon">&#x25BC;</span>
                </div>
            </div>
            
            <!-- Expanded Content -->
            <div class="thread-card-expanded-content">
                <div class="message-view-header" style="border-bottom: 1px solid var(--border); padding-bottom: 15px; margin-bottom: 15px;">
                    <div class="msg-avatar msg-avatar-lg" style="background:<?php echo get_avatar_color($tmsg['sender_name']); ?>">
                        <?php echo e(get_initials($tmsg['sender_name'])); ?>
                    </div>
                    <div class="msg-view-info" style="flex:1;">
                        <div style="display:flex; justify-content:between; align-items:flex-start; width:100%; gap: 20px;">
                            <div style="flex:1;">
                                <span class="msg-view-sender" style="font-weight:600; font-size:15px; color:var(--text);"><?php echo e($tmsg['sender_name']); ?></span>
                                <span style="font-size:13px; color:var(--text3); font-weight:normal;">(@<?php echo e($tmsg['sender_username']); ?>)</span>
                            </div>
                            <span class="msg-view-date" style="color:var(--text3); font-size:13px;"><?php echo format_date($tmsg['sent_at'] ? $tmsg['sent_at'] : $tmsg['created_at']); ?></span>
                        </div>
                        <div class="msg-view-recipients" style="margin-top:4px; font-size:13px; color:var(--text2);">
                            <span style="color:var(--text3);">To:</span>
                            <?php foreach ($tToList as $i => $r): ?><?php echo ($i > 0 ? ', ' : '') . e($r['display_name']); ?><?php endforeach; ?>
                            <?php if (!empty($tCcList)): ?>
                                <br><span style="color:var(--text3);">CC:</span>
                                <?php foreach ($tCcList as $i => $r): ?><?php echo ($i > 0 ? ', ' : '') . e($r['display_name']); ?><?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <!-- Contextual Actions on Card -->
                    <div class="thread-card-actions" onclick="event.stopPropagation()">
                        <?php if (!$tisRetracted): ?>
                            <?php if (!$tisSender): ?>
                                <a href="index.php?page=compose&reply=<?php echo $tId; ?>" class="btn btn-xs btn-primary">&#x21A9; Reply</a>
                                <a href="index.php?page=compose&replyall=<?php echo $tId; ?>" class="btn btn-xs btn-secondary">&#x21A9; Reply All</a>
                            <?php endif; ?>
                            <a href="index.php?page=compose&forward=<?php echo $tId; ?>" class="btn btn-xs btn-secondary">&#x21AA; Forward</a>
                        <?php endif; ?>
                        <?php if ($tisSender && !$tisRetracted): ?>
                            <button class="btn btn-xs btn-warning" onclick="retractMessage(<?php echo $tId; ?>)">&#x21A9; Unsend</button>
                        <?php endif; ?>
                        <button class="btn btn-xs btn-danger" onclick="deleteMessage(<?php echo $tId; ?>,'<?php echo e($backPage); ?>')">&#x1F5D1; Delete</button>
                    </div>
                </div>
                
                <?php if ($tisRetracted): ?>
                    <div class="alert alert-error" style="text-align:center; padding:30px; font-size:14px; margin:15px 0;">
                        &#x26A0; This message was retracted by the sender.
                    </div>
                <?php else: ?>
                    <?php if (!empty($tatts)): ?>
                        <div class="attachments-section" style="margin-bottom:15px; border-bottom:1px solid var(--border); padding-bottom:15px;">
                            <h4 style="font-size:13px; margin:0 0 10px 0;">&#x1F4CE; Attachments (<?php echo count($tatts); ?>)</h4>
                            <div class="attachment-list" style="gap:6px;">
                                <?php foreach ($tatts as $att):
                                    $decayInfo = get_attachment_decay_info($att['created_at']);
                                    $isExpired = $decayInfo['expired'];
                                    $fileExists = $isExpired ? false : file_exists(UPLOAD_DIR . $att['stored_name']);
                                ?>
                                    <?php if ($fileExists): 
                                        $ext = strtolower(pathinfo($att['original_name'], PATHINFO_EXTENSION));
                                        $isPreviewable = in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp', 'pdf', 'txt']);
                                        $previewUrl = "api/attachments.php?action=preview&id=" . $att['id'];
                                        $downloadUrl = "api/attachments.php?action=download&id=" . $att['id'];
                                    ?>
                                        <div class="attachment-item" style="padding:0; overflow:hidden; display:flex; align-items:stretch; height:32px;">
                                            <a href="<?php echo $isPreviewable ? 'javascript:void(0)' : $downloadUrl; ?>" 
                                               <?php if($isPreviewable) echo 'onclick="openPreview(\''.$previewUrl.'\', \''.e(addslashes($att['original_name'])).'\', \''.$ext.'\')"'; ?>
                                               style="display:flex; align-items:center; gap:8px; padding:6px 12px; flex:1; text-decoration:none; color:var(--text); font-size:12px;">
                                                <span class="att-icon">&#x1F4C4;</span>
                                                <span class="att-name"><?php echo e($att['original_name']); ?></span>
                                                <span class="att-size" style="color:var(--text3); font-size:11px;"><?php echo format_size($att['file_size']); ?></span>
                                                <span class="att-decay-badge <?php echo $decayInfo['class']; ?>" title="Time remaining before this attachment decays" style="font-size:10px; padding:2px 6px;">
                                                    ⏱️ <?php echo $decayInfo['text']; ?>
                                                </span>
                                            </a>
                                            <a href="<?php echo $downloadUrl; ?>" title="Download" style="display:flex; align-items:center; justify-content:center; padding:0 10px; border-left:1px solid var(--border); text-decoration:none; color:var(--text2); transition:background 0.15s;" onmouseover="this.style.background='var(--accent-glow)';" onmouseout="this.style.background='transparent';">
                                                &#x1F4E5;
                                            </a>
                                        </div>
                                    <?php else: ?>
                                        <?php if ($isExpired): ?>
                                            <span class="attachment-item att-removed" title="File expired (limit: 30 days)" style="opacity: 0.7; gap: 8px; font-size:12px; padding:6px 12px;">
                                                <span class="att-icon">&#x23F1;</span>
                                                <span class="att-name" style="text-decoration: line-through; color: var(--text3);"><?php echo e($att['original_name']); ?></span>
                                                <span class="att-size" style="color: var(--text3);"><?php echo format_size($att['file_size']); ?> - Expired</span>
                                            </span>
                                        <?php else: ?>
                                            <span class="attachment-item att-removed" title="File removed after all recipients read this message" style="font-size:12px; padding:6px 12px;">
                                                <span class="att-icon">&#x1F6AB;</span>
                                                <span class="att-name"><?php echo e($att['original_name']); ?></span>
                                                <span class="att-size"><?php echo format_size($att['file_size']); ?> - removed</span>
                                            </span>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endif; ?>
                    
                    <div class="message-body" style="line-height: 1.6; font-size: 15px; color: var(--text);"><?php echo sanitize_html($tmsg['body']); ?></div>
                    
                    <!-- Dynamic nested forward chain -->
                    <?php if (!empty($tmsg['forwarded_from_id'])):
                        $fwdChain = get_forwarded_message_chain($conn, $tmsg['forwarded_from_id'], $userId);
                        if ($fwdChain):
                            render_forwarded_chain_html($fwdChain);
                        endif;
                    endif; ?>
                <?php endif; ?>
            </div>
        </div>
    <?php endforeach; ?>
</div>

<script>
function toggleThreadCard(msgId, event) {
    // If the click is inside a button, link, or action block, do not toggle the card
    if (event.target.closest('a') || event.target.closest('button') || event.target.closest('.thread-card-actions') || event.target.closest('.attachment-item')) {
        return;
    }
    var card = document.getElementById('thread-card-' + msgId);
    if (card) {
        card.classList.toggle('collapsed');
        card.classList.toggle('expanded');
    }
}
</script>

