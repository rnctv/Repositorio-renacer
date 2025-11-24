<?php
  $curSort = request('sort','id_externo');
  $curDir  = request('dir','asc');
  $nextDir = function($col) use ($curSort,$curDir){ return ($curSort===$col && $curDir==='asc') ? 'desc' : 'asc'; };
  $qbase   = request()->except(['sort','dir','page','partial']);
  function sort_url($col,$dir,$qbase){ return route('clientes.index', array_merge($qbase,['sort'=>$col,'dir'=>$dir])); }
  function arrow($col,$curSort,$curDir){ if($curSort!==$col) return '↕'; return $curDir==='asc'?'▲':'▼'; }
?>

<div class="table-wrap">
  <table>
    <thead>
      <tr>
        <th class="th">
          <a href="<?php echo e(sort_url('id_externo', $nextDir('id_externo'), $qbase)); ?>">
            ID <span class="arrow"><?php echo e(arrow('id_externo',$curSort,$curDir)); ?></span>
          </a>
        </th>
        <th class="th">
          <a href="<?php echo e(sort_url('nombre', $nextDir('nombre'), $qbase)); ?>">
            Nombre <span class="arrow"><?php echo e(arrow('nombre',$curSort,$curDir)); ?></span>
          </a>
        </th>
        <th class="th">
          <a href="<?php echo e(sort_url('direccion', $nextDir('direccion'), $qbase)); ?>">
            Dirección <span class="arrow"><?php echo e(arrow('direccion',$curSort,$curDir)); ?></span>
          </a>
        </th>
        <th class="th">
          <a href="<?php echo e(sort_url('movil', $nextDir('movil'), $qbase)); ?>">
            Móvil <span class="arrow"><?php echo e(arrow('movil',$curSort,$curDir)); ?></span>
          </a>
        </th>
        <th class="th">
          <a href="<?php echo e(sort_url('el_plan', $nextDir('el_plan'), $qbase)); ?>">
            Plan <span class="arrow"><?php echo e(arrow('el_plan',$curSort,$curDir)); ?></span>
          </a>
        </th>
      </tr>
    </thead>
    <tbody>
      <?php $__currentLoopData = $clientes; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $cli): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
        <tr>
          <td data-label="ID"><?php echo e($cli->id_externo ?: '—'); ?></td>
          <td data-label="Nombre"><a class="link cliente-link" href="#" data-id="<?php echo e($cli->id); ?>"><?php echo e($cli->nombre ?: '—'); ?></a></td>
          <td data-label="Dirección"><?php echo e($cli->direccion ?: '—'); ?></td>
          <td data-label="Móvil"><?php echo e($cli->movil ?: '—'); ?></td>
          <td data-label="Plan"><?php echo e($cli->el_plan ?: $cli->plan ?: '—'); ?></td>
        </tr>
      <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
    </tbody>
  </table>
</div>

<div style="margin-top:1rem;">
  <?php echo e($clientes->links('components.pagination')); ?>

</div>
<?php /**PATH /var/www/laravel/resources/views/clientes/partials/table.blade.php ENDPATH**/ ?>