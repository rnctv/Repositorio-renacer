<?php $__env->startSection('title','Clientes'); ?>

<?php
  // Orden por defecto id_externo ASC
  $curSort = request('sort','id_externo');
  $curDir  = request('dir','asc');
?>

<?php $__env->startSection('content'); ?>
<div class="card">
  <div class="card-body">
    <form method="get" class="toolbar" action="<?php echo e(route('clientes.index')); ?>">
      <div class="input-wrap">
        <input class="input" type="text" name="s" id="filtro-s"
               value="<?php echo e(request('s')); ?>" placeholder="Filtrar..." autocomplete="off" />
        <button type="button" class="clear-btn" id="btn-clear-s" aria-label="Limpiar">✕</button>
      </div>

      <select name="estado" class="select">
        <option value="">Estado: Todos</option>
        <?php $__currentLoopData = ['ACTIVO','SUSPENDIDO','RETIRADO']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $st): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
          <option value="<?php echo e($st); ?>" <?php if(request('estado')===$st): echo 'selected'; endif; ?>><?php echo e($st); ?></option>
        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
      </select>

      <input type="hidden" name="sort" value="<?php echo e($curSort); ?>" />
      <input type="hidden" name="dir"  value="<?php echo e($curDir); ?>" />

      <button class="btn" type="button" onclick="location.href='<?php echo e(route('clientes.index')); ?>'">Reset</button>
      <button class="btn primary">Buscar</button>
      <a href="<?php echo e(route('clientes.form')); ?>" class="btn">Importar</a>
    </form>

    
    <div id="clientes-area">
      <?php echo $__env->make('clientes.partials.table', ['clientes'=>$clientes], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
    </div>
  </div>
</div>


<script src="<?php echo e(asset('js/clientes.js')); ?>?v=<?php echo e($assetManifest['js/clientes.js'] ?? ''); ?>"></script>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH /var/www/laravel/resources/views/clientes/index.blade.php ENDPATH**/ ?>