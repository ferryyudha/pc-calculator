import React, { useMemo } from 'react';

export default function HistoryCharts({ logs, metric, label, color = '#00D4FF' }) {
  const width = 600;
  const height = 220;
  const paddingLeft = 40;
  const paddingRight = 15;
  const paddingTop = 20;
  const paddingBottom = 30;

  const chartWidth = width - paddingLeft - paddingRight;
  const chartHeight = height - paddingTop - paddingBottom;

  // Extract points
  const points = useMemo(() => {
    if (!logs || logs.length === 0) return [];
    return logs.map((log) => {
      let val = 0;
      if (metric === 'cpu_temp') val = log.cpu_temp || 0;
      else if (metric === 'gpu_temp') val = log.gpu_temp || 0;
      else if (metric === 'cpu_usage') val = log.cpu_usage || 0;
      else if (metric === 'gpu_usage') val = log.gpu_usage || 0;
      else if (metric === 'ram_usage') val = log.ram_usage || 0;
      else if (metric === 'power_usage') val = log.power_usage || 0;

      return {
        value: val,
        time: new Date(log.created_at).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' }),
        rawTime: log.created_at
      };
    });
  }, [logs, metric]);

  const stats = useMemo(() => {
    if (points.length === 0) return { min: 0, max: 100 };
    const values = points.map(p => p.value);
    let minVal = Math.min(...values);
    let maxVal = Math.max(...values);
    
    // Add buffer
    if (maxVal - minVal < 5) {
      maxVal += 5;
      minVal = Math.max(0, minVal - 5);
    } else {
      const diff = maxVal - minVal;
      maxVal += diff * 0.1;
      minVal = Math.max(0, minVal - diff * 0.1);
    }
    
    // Round to nice numbers
    maxVal = Math.ceil(maxVal);
    minVal = Math.floor(minVal);

    return { min: minVal, max: maxVal };
  }, [points]);

  const { pathD, areaD, gridLines, labels } = useMemo(() => {
    if (points.length === 0) return { pathD: '', areaD: '', gridLines: [], labels: [] };

    const total = points.length;
    const { min, max } = stats;

    const coords = points.map((p, index) => {
      const x = paddingLeft + (total > 1 ? index / (total - 1) : 0) * chartWidth;
      const range = max - min || 1;
      const y = paddingTop + chartHeight - ((p.value - min) / range) * chartHeight;
      return { x, y, ...p };
    });

    // Generate Path
    let pD = '';
    coords.forEach((c, i) => {
      if (i === 0) pD += `M ${c.x} ${c.y}`;
      else pD += ` L ${c.x} ${c.y}`;
    });

    // Generate Area Path (closed below line)
    let aD = '';
    if (coords.length > 0) {
      aD = `${pD} L ${coords[coords.length - 1].x} ${paddingTop + chartHeight} L ${coords[0].x} ${paddingTop + chartHeight} Z`;
    }

    // Grid lines (3 horizontal lines)
    const gl = [];
    const linesCount = 3;
    for (let i = 0; i <= linesCount; i++) {
      const val = min + (i / linesCount) * (max - min);
      const y = paddingTop + chartHeight - (i / linesCount) * chartHeight;
      gl.push({
        y,
        value: Math.round(val)
      });
    }

    // X axis labels (approx 4 labels)
    const labs = [];
    const labelStep = Math.max(1, Math.floor(total / 4));
    for (let i = 0; i < total; i += labelStep) {
      labs.push(coords[i]);
    }
    // Always include last if not already included
    if (total > 1 && (total - 1) % labelStep !== 0) {
      labs.push(coords[total - 1]);
    }

    return { pathD: pD, areaD: aD, gridLines: gl, labels: labs };
  }, [points, stats, chartWidth, chartHeight]);

  if (points.length === 0) {
    return (
      <div style={{ height }} className="flex items-center justify-center text-gray-500 font-medium text-sm">
        Tidak ada data riwayat yang tersedia.
      </div>
    );
  }

  return (
    <div className="w-full">
      <div className="flex justify-between items-center mb-4">
        <h4 className="text-sm text-gray-300 font-semibold">{label}</h4>
        <div className="text-sm font-bold font-mono" style={{ color: color }}>
          Terbaru: {points[points.length - 1].value.toFixed(1)}
        </div>
      </div>

      <svg viewBox={`0 0 ${width} ${height}`} width="100%" height="auto" style={{ overflow: 'visible' }}>
        <defs>
          <linearGradient id={`grad-${metric}`} x1="0" y1="0" x2="0" y2="1">
            <stop offset="0%" stopColor={color} stopOpacity="0.25" />
            <stop offset="100%" stopColor={color} stopOpacity="0.0" />
          </linearGradient>
        </defs>

        {/* Grid Lines */}
        {gridLines.map((gl, i) => (
          <g key={i}>
            <line 
              x1={paddingLeft} 
              y1={gl.y} 
              x2={width - paddingRight} 
              y2={gl.y} 
              stroke="rgba(255,255,255,0.05)" 
              strokeWidth="1"
              strokeDasharray="4 4"
            />
            <text 
              x={paddingLeft - 8} 
              y={gl.y + 4} 
              fill="#64748b" 
              fontSize="10" 
              textAnchor="end"
              className="font-sans font-medium"
            >
              {gl.value}
            </text>
          </g>
        ))}

        {/* Area */}
        <path d={areaD} fill={`url(#grad-${metric})`} />

        {/* Line */}
        <path 
          d={pathD} 
          fill="none" 
          stroke={color} 
          strokeWidth="2.5" 
          strokeLinecap="round" 
          strokeLinejoin="round" 
        />

        {/* X Axis labels */}
        {labels.map((lab, i) => (
          <text 
            key={i} 
            x={lab.x} 
            y={height - 8} 
            fill="#64748b" 
            fontSize="10" 
            textAnchor="middle"
            className="font-sans font-medium"
          >
            {lab.time}
          </text>
        ))}

        {/* Dot on latest point */}
        {points.length > 0 && (
          <circle 
            cx={paddingLeft + chartWidth} 
            cy={paddingTop + chartHeight - ((points[points.length - 1].value - stats.min) / (stats.max - stats.min || 1)) * chartHeight}
            r="4.5"
            fill={color}
            stroke="#0A0F1D"
            strokeWidth="1.5"
          />
        )}
      </svg>
    </div>
  );
}
