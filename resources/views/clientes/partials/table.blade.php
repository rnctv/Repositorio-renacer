@php
  $curSort = request('sort','id_externo');
  $curDir  = request('dir','asc');
  $nextDir = function($col) use ($curSort,$curDir){ return ($curSort===$col && $curDir==='asc') ? 'desc' : 'asc'; };
  $qbase   = request()->except(['sort','dir','page','partial']);
  function sort_url($col,$dir,$qbase){ return route('clientes.index', array_merge($qbase,['sort'=>$col,'dir'=>$dir])); }
  function arrow($col,$curSort,$curDir){ if($curSort!==$col) return '↕'; return $curDir==='asc'?'▲':'▼'; }
@endphp

<div class="table-wrap">
  <table>
    <thead>
      <tr>
        <th class="th">
          <a href="{{ sort_url('id_externo', $nextDir('id_externo'), $qbase) }}">
            ID <span class="arrow">{{ arrow('id_externo',$curSort,$curDir) }}</span>
          </a>
        </th>
        <th class="th">
          <a href="{{ sort_url('nombre', $nextDir('nombre'), $qbase) }}">
            Nombre <span class="arrow">{{ arrow('nombre',$curSort,$curDir) }}</span>
          </a>
        </th>
        <th class="th">
          <a href="{{ sort_url('direccion', $nextDir('direccion'), $qbase) }}">
            Dirección <span class="arrow">{{ arrow('direccion',$curSort,$curDir) }}</span>
          </a>
        </th>
        <th class="th">
          <a href="{{ sort_url('movil', $nextDir('movil'), $qbase) }}">
            Móvil <span class="arrow">{{ arrow('movil',$curSort,$curDir) }}</span>
          </a>
        </th>
        <th class="th">
          <a href="{{ sort_url('el_plan', $nextDir('el_plan'), $qbase) }}">
            Plan <span class="arrow">{{ arrow('el_plan',$curSort,$curDir) }}</span>
          </a>
        </th>
      </tr>
    </thead>
    <tbody>
      @foreach ($clientes as $cli)
        <tr>
          <td data-label="ID">{{ $cli->id_externo ?: '—' }}</td>
          <td data-label="Nombre"><a class="link cliente-link" href="#" data-id="{{ $cli->id }}">{{ $cli->nombre ?: '—' }}</a></td>
          <td data-label="Dirección">{{ $cli->direccion ?: '—' }}</td>
          <td data-label="Móvil">{{ $cli->movil ?: '—' }}</td>
          <td data-label="Plan">{{ $cli->el_plan ?: $cli->plan ?: '—' }}</td>
        </tr>
      @endforeach
    </tbody>
  </table>
</div>

<div style="margin-top:1rem;">
  {{ $clientes->links('components.pagination') }}
</div>
