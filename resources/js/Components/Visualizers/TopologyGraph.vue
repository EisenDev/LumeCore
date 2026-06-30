<script setup lang="ts">
import { onMounted, ref, watch, computed } from 'vue';
import { 
    Home, Globe, Database, Server, FileText, 
    AlertTriangle, Shield, Cpu, Activity,
    Box, Layout, Link as LinkIcon, Zap,
    Monitor, Mail, HardDrive, Lock
} from 'lucide-vue-next';
// @ts-ignore
import * as d3 from 'd3';

interface Node {
    id: string;
    label: string;
    status: number;
    type?: string;
    x?: number;
    y?: number;
    fx?: number | null;
    fy?: number | null;
}

interface Link {
    source: string | Node;
    target: string | Node;
    type?: string;
}

const props = withDefaults(defineProps<{
    data: { nodes: Node[], links: Link[] };
    hideHud?: boolean;
}>(), {
    hideHud: false
});

const container = ref<HTMLElement | null>(null);
const hoveredNode = ref<Node | null>(null);
const currentTransform = ref({ x: 0, y: 0, k: 1 });
const zoomBehavior = ref<any>(null);

// Spoke angle mapping calibrated clockwise starting from top (-90 deg)
const getSpokeAngle = (id: string): number | null => {
    const name = id.toLowerCase();
    if (name.includes('www.')) return -Math.PI / 2;
    if (name.includes('waf') || name.includes('firewall')) return 3 * Math.PI / 4;
    if (name.includes('cdn.')) return Math.PI;
    if (name.includes('api.')) return 5 * Math.PI / 4;
    if (name.includes('mysql') || name.includes('database') || name.includes('db')) return 0;
    if (name.includes('s3') || name.includes('storage') || name.includes('bucket')) return Math.PI / 4;
    if (name.includes('mail') || name.includes('email') || name.includes('smtp')) return Math.PI / 2;
    if (name.includes('cloudflare')) return -Math.PI / 4;
    return null;
};

// Icon Mapping
const getIconComponent = (node: Node) => {
    const name = node.id.toLowerCase();
    const type = node.type?.toLowerCase() || '';
    
    if (type === 'root' || node.id === props.data.nodes[0]?.id) return Monitor;
    if (name.includes('waf') || name.includes('firewall')) return Shield;
    if (name.includes('cloudflare')) return Shield;
    if (name.includes('mysql') || type === 'database') return Database;
    if (name.includes('s3') || name.includes('bucket') || name.includes('storage')) return HardDrive;
    if (name.includes('mail') || name.includes('email') || name.includes('smtp')) return Mail;
    if (name.includes('api')) return Zap;
    if (name.includes('cdn')) return Cpu;
    if (name.includes('www')) return Globe;
    return Globe;
};

// Color Mapping
const getNodeColor = (node: Node) => {
    if (node.type === 'root' || node.id === props.data.nodes[0]?.id) return '#f43f5e'; // Rose/Red for center
    const name = node.id.toLowerCase();
    if (name.includes('www') || name.includes('api') || name.includes('cdn')) return '#10b981'; // Emerald Green
    if (name.includes('mysql') || node.type?.toLowerCase() === 'database') return '#3b82f6'; // Blue
    if (name.includes('s3') || name.includes('bucket') || name.includes('storage')) return '#06b6d4'; // Cyan
    if (name.includes('mail') || name.includes('email') || name.includes('smtp')) return '#a855f7'; // Purple
    if (name.includes('waf') || name.includes('firewall') || name.includes('security')) return '#6366f1'; // Indigo
    if (name.includes('cloudflare')) return '#f59e0b'; // Amber/Gold
    return '#3b82f6'; // Blue default
};

const getNodeBgColor = (node: Node) => {
    const color = getNodeColor(node);
    return `${color}15`; // 10% opacity background glow
};

const getNodeBadge = (node: Node) => {
    const name = node.id.toLowerCase();
    if (name.includes('mysql') || name.includes('database')) return 'TCP : 3306';
    if (name.includes('mail') || name.includes('smtp')) return 'SMTP';
    if (name.includes('www.') || name.includes('api.') || name.includes('cdn.') || name.includes('s3') || name.includes('cloudflare') || name.includes('waf')) return 'HTTPS';
    return null;
};

const renderGraph = () => {
    if (!container.value || !props.data.nodes?.length) return;

    // Clear previous SVG
    d3.select(container.value).select("svg").remove();

    const width = container.value.clientWidth;
    const height = container.value.clientHeight;

    const svg = d3.select(container.value)
        .append("svg")
        .attr("class", "absolute inset-0 z-10")
        .attr("width", width)
        .attr("height", height)
        .attr("viewBox", [0, 0, width, height]);

    const g = svg.append("g");

    // Zoom Behavior
    const zoom = d3.zoom()
        .scaleExtent([0.3, 2.5])
        .on("zoom", (event) => {
            currentTransform.value = event.transform;
            g.attr("transform", event.transform);
        });

    zoomBehavior.value = zoom;
    svg.call(zoom);
    svg.call(zoom.transform, d3.zoomIdentity);

    // Deep copy data
    const nodes = props.data.nodes.map(d => ({ ...d }));
    const links = props.data.links.map(d => ({ ...d }));

    // Calibrate radial coordinates around center
    const cx = width / 2;
    const cy = height / 2;
    const R = Math.min(width, height) * 0.38;

    // Concéntric dashed grid lines
    const gridRadii = [100, R, R + 100];
    gridRadii.forEach(r => {
        g.append("circle")
            .attr("cx", cx)
            .attr("cy", cy)
            .attr("r", r)
            .attr("fill", "none")
            .attr("stroke", "rgba(255, 255, 255, 0.025)")
            .attr("stroke-width", 1)
            .attr("stroke-dasharray", "4, 6");
    });

    nodes.forEach(node => {
        if (node.type === 'root' || node.id === props.data.nodes[0].id) {
            node.fx = cx;
            node.fy = cy;
            node.x = cx;
            node.y = cy;
        } else {
            const angle = getSpokeAngle(node.id);
            if (angle !== null) {
                node.fx = cx + R * Math.cos(angle);
                node.fy = cy + R * Math.sin(angle);
                node.x = node.fx;
                node.y = node.fy;
            }
        }
    });

    // D3 Simulation setup
    const simulation = d3.forceSimulation(nodes as any)
        .force("link", d3.forceLink(links).id((d: any) => d.id).distance(R))
        .force("charge", d3.forceManyBody().strength(-300))
        .force("center", d3.forceCenter(cx, cy));

    // Base connection lines
    const link = g.append("g")
        .selectAll("path")
        .data(links)
        .join("path")
        .attr("stroke", (d: any) => {
            const color = getNodeColor(d.target);
            return `${color}35`; // Dim link line matching target node color
        })
        .attr("stroke-width", 1.5)
        .attr("fill", "none");

    // Marching data traffic flow lines
    const linkFlow = g.append("g")
        .selectAll("path")
        .data(links)
        .join("path")
        .attr("stroke", (d: any) => {
            const color = getNodeColor(d.target);
            return `${color}75`; // Glowing marching line
        })
        .attr("stroke-width", 2)
        .attr("stroke-dasharray", "6, 12")
        .attr("fill", "none")
        .classed("route-dash-animated", true);

    // Physics Drag setup
    const node = g.append("g")
        .selectAll("circle")
        .data(nodes)
        .join("circle")
        .attr("r", 24)
        .attr("fill", "transparent")
        .attr("cursor", "grab")
        .call(d3.drag()
            .on("start", dragstarted)
            .on("drag", dragged)
            .on("end", dragended) as any);

    simulation.on("tick", () => {
        link.attr("d", (d: any) => `M${d.source.x},${d.source.y}L${d.target.x},${d.target.y}`);
        linkFlow.attr("d", (d: any) => `M${d.source.x},${d.source.y}L${d.target.x},${d.target.y}`);
        node.attr("cx", (d: any) => d.x).attr("cy", (d: any) => d.y);
        iconNodes.value = [...nodes];
    });

    // Drag handlers
    function dragstarted(event: any, d: any) {
        if (!event.active) simulation.alphaTarget(0.2).restart();
        d.fx = d.x;
        d.fy = d.y;
    }

    function dragged(event: any, d: any) {
        d.fx = event.x;
        d.fy = event.y;
    }

    // Spring Snap-Back Animation on Release
    function dragended(event: any, d: any) {
        if (!event.active) simulation.alphaTarget(0);

        let targetX = cx;
        let targetY = cy;
        if (!(d.type === 'root' || d.id === props.data.nodes[0].id)) {
            const angle = getSpokeAngle(d.id);
            if (angle !== null) {
                targetX = cx + R * Math.cos(angle);
                targetY = cy + R * Math.sin(angle);
            }
        }

        const startX = d.x;
        const startY = d.y;
        const duration = 500;
        const startTime = performance.now();

        function snapTick(now: number) {
            const elapsed = now - startTime;
            const t = Math.min(elapsed / duration, 1);
            const ease = 1 - Math.pow(1 - t, 3); // Cubic ease out
            d.fx = startX + (targetX - startX) * ease;
            d.fy = startY + (targetY - startY) * ease;
            if (t < 1) {
                requestAnimationFrame(snapTick);
            } else {
                d.fx = targetX;
                d.fy = targetY;
            }
        }
        requestAnimationFrame(snapTick);
    }
};

const handleZoomIn = () => {
    if (!container.value || !zoomBehavior.value) return;
    const svg = d3.select(container.value).select("svg");
    svg.transition().duration(250).call(zoomBehavior.value.scaleBy as any, 1.25);
};

const handleZoomOut = () => {
    if (!container.value || !zoomBehavior.value) return;
    const svg = d3.select(container.value).select("svg");
    svg.transition().duration(250).call(zoomBehavior.value.scaleBy as any, 0.8);
};

const resetZoom = () => {
    if (container.value && zoomBehavior.value) {
        const svg = d3.select(container.value).select("svg");
        svg.transition().duration(750).call(zoomBehavior.value.transform, d3.zoomIdentity);
    }
};

const iconNodes = ref<any[]>([]);

const emit = defineEmits<{
    (e: 'node-click', node: any): void;
}>();

watch(() => props.data, renderGraph, { deep: true });

onMounted(() => {
    setTimeout(renderGraph, 100);
});
</script>

<template>
    <div class="w-full h-full relative group overflow-hidden rounded-xl cursor-grab active:cursor-grabbing bg-[#07080a] border border-white/5 shadow-inner" ref="container">
        
        <!-- Floating zoom/fit/lock controls - bottom-left -->
        <div v-if="!hideHud" class="absolute bottom-6 left-6 z-40 flex items-center gap-1 bg-slate-900/90 backdrop-blur border border-white/10 p-1.5 rounded-lg shadow-xl pointer-events-auto">
            <button @click="handleZoomIn" class="w-8 h-8 flex items-center justify-center rounded-md text-slate-400 hover:text-white hover:bg-white/5 transition-all text-sm font-bold select-none">+</button>
            <button @click="handleZoomOut" class="w-8 h-8 flex items-center justify-center rounded-md text-slate-400 hover:text-white hover:bg-white/5 transition-all text-sm font-bold select-none">-</button>
            <button @click="resetZoom" class="w-8 h-8 flex items-center justify-center rounded-md text-slate-400 hover:text-white hover:bg-white/5 transition-all" title="Fit to View">
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 3.75v4.5m0-4.5h4.5m-4.5 0L9 9M3.75 20.25v-4.5m0 4.5h4.5m-4.5 0L9 15M20.25 3.75v4.5m0-4.5h-4.5m4.5 0L15 9m5.25 11.25v-4.5m0 4.5h-4.5m4.5 0L15 15"/>
                </svg>
            </button>
            <button class="w-8 h-8 flex items-center justify-center rounded-md text-slate-500 hover:text-slate-350 hover:bg-white/5 transition-all" title="Lock Layout">
                <Lock class="w-3.5 h-3.5" />
            </button>
        </div>

        <!-- Concentric Radial Grid overlay -->
        <div v-if="!hideHud" class="absolute inset-0 pointer-events-none opacity-[0.03] bg-[radial-gradient(ellipse_at_center,_var(--tw-gradient-stops))] from-white via-transparent to-transparent"></div>

        <!-- 4. Interactive Nodes (Transformed Wrapper) -->
        <div class="absolute inset-0 pointer-events-none z-20" :style="{ transform: `translate(${currentTransform.x}px, ${currentTransform.y}px) scale(${currentTransform.k})`, transformOrigin: '0 0' }">
            <div 
                v-for="node in iconNodes" 
                :key="node.id"
                class="absolute transform -translate-x-1/2 -translate-y-1/2 flex items-center justify-center will-change-transform pointer-events-auto"
                :style="{ left: `${node.x}px`, top: `${node.y}px`, width: '48px', height: '48px' }"
                @mouseenter="hoveredNode = node"
                @mouseleave="hoveredNode = null"
            >
                <!-- Node Wrapper -->
                <div 
                    class="relative w-12 h-12 flex items-center justify-center cursor-pointer transition-transform duration-300" 
                    :class="{'scale-110': hoveredNode?.id === node.id}"
                    @click="emit('node-click', node)"
                >
                    <!-- Central Node Special Red Glow -->
                    <div v-if="node.type === 'root' || node.id === props.data.nodes[0]?.id" class="absolute inset-[-4px] rounded-full bg-rose-500/20 blur-md animate-pulse"></div>
                    <div v-if="node.type === 'root' || node.id === props.data.nodes[0]?.id" class="absolute inset-[-6px] rounded-full border border-rose-500/35 opacity-75 animate-ping" style="animation-duration: 3s;"></div>
                    
                    <!-- Circular indicator and background -->
                    <div 
                        class="absolute inset-0 rounded-full border transition-all duration-300"
                        :style="{ 
                            borderColor: getNodeColor(node), 
                            backgroundColor: getNodeBgColor(node),
                            boxShadow: hoveredNode?.id === node.id ? `0 0 16px ${getNodeColor(node)}` : `0 0 8px ${getNodeColor(node)}1c`
                        }"
                    ></div>
                    
                    <!-- Icon component -->
                    <component 
                        :is="getIconComponent(node)" 
                        class="w-5 h-5 relative z-10" 
                        :style="{ color: getNodeColor(node) }"
                    />
                </div>

                <!-- Always visible clean text label below/above each node -->
                <div class="absolute flex flex-col items-center pointer-events-none w-48 text-center select-none" style="transform: translate(-50%, 28px); left: 50%;">
                    <span class="text-[10px] font-bold text-white font-mono leading-tight max-w-[130px] truncate">{{ node.id === '/' ? props.data.nodes[0].id : node.id }}</span>
                    <span class="text-[9px] text-slate-500 font-mono mt-0.5 leading-none font-bold uppercase tracking-wider">{{ node.label }}</span>
                    <span v-if="getNodeBadge(node)" 
                          :style="{ color: getNodeColor(node), background: getNodeBgColor(node), borderColor: `${getNodeColor(node)}25` }" 
                          class="text-[8px] font-mono px-1 py-0.2 rounded border mt-1 font-bold uppercase tracking-wider leading-none"
                    >
                        {{ getNodeBadge(node) }}
                    </span>
                </div>
            </div>
        </div>
    </div>
</template>

<style scoped>
/* Matching marching traffic flow animation */
@keyframes dash-march {
    to { stroke-dashoffset: -36; }
}
:deep(.route-dash-animated) {
    stroke-dashoffset: 0;
    animation: dash-march 1.5s linear infinite;
}
</style>
