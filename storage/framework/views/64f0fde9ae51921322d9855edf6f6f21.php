<?php $__env->startSection('content'); ?>
<div class="container mt-4">

    <h2 class="mb-4">📥 Mensajes Entrantes de WhatsApp</h2>

    <div class="card shadow-sm">
        <div class="card-body" style="max-height: 600px; overflow-y: auto;">

            <?php $__currentLoopData = $messages; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $msg): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                <div class="p-3 mb-3 border rounded" style="background: #f5f5f5;">
                    <strong><?php echo e($msg->from_number); ?></strong>
                    <small class="text-muted float-end"><?php echo e($msg->received_at); ?></small>

                    <p class="mt-2 mb-0"><?php echo e($msg->message); ?></p>
                </div>
            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>

        </div>
    </div>

</div>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH /var/www/laravel/resources/views/whatsapp/inbox.blade.php ENDPATH**/ ?>