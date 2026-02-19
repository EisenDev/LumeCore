<script setup lang="ts">
import { onMounted, ref, watch, computed } from 'vue';
import { 
    Home, Globe, Database, Server, FileText, 
    AlertTriangle, Shield, Cpu, Activity,
    Box, Layout, Link as LinkIcon, Zap
} from 'lucide-vue-next';
// @ts-ignore
import * as d3 from 'd3';

interface Node {
    id: string; // URL path like /about
    status: number;
    type?: string;
    x?: number;
    y?: number;
    fx?: number | null;
    fy?: number | null;
    redirect?: boolean;
}

interface Link {
    source: string | Node;
    target: string | Node;
}

const props = defineProps<{
    data: { nodes: Node[], links: Link[] }
}>();

const container = ref<HTMLElement | null>(null);
const hoveredNode = ref<Node | null>(null);
const currentTransform = ref({ x: 0, y: 0, k: 1 });
const zoomBehavior = ref<any>(null);

// Stats Computation
const stats = computed(() => {
    const nodes = props.data.nodes || [];
    const total = nodes.length;
    const critical = nodes.filter(n => n.status >= 400).length;
    const healthy = total - critical;
    const integrity = total > 0 ? Math.round((healthy / total) * 100) : 100;
    return { total, critical, healthy, integrity };
});

// Icon Mapping
const getIconComponent = (node: Node) => {
    const type = node.type?.toLowerCase() || '';
    const name = node.id?.toLowerCase() || '';
    
    if (name === '' || name === '/' || type === 'root') return Globe;
    if (name.includes('api') || type.includes('api')) return Database;
    if (name.includes('admin') || name.includes('dashboard')) return Layout;
    if (name.includes('login') || name.includes('auth')) return Shield;
    if (name.includes('blog') || name.includes('post')) return FileText;
    if (type === 'css' || type === 'script') return FileText;
    if (node.status >= 400) return AlertTriangle;
    if (node.redirect) return LinkIcon;
    return Activity;
};

// Color Mapping
const getNodeColor = (node: Node) => {
    if (node.status >= 400) return '#ef4444'; // Red-500
    if (node.redirect) return '#f59e0b'; // Amber-500
    if (node.id === '/') return '#8b5cf6'; // Violet-500
    if (node.id.includes('api')) return '#06b6d4'; // Cyan-500
    return '#3b82f6'; // Blue-500
};

const renderGraph = () => {
    if (!container.value || !props.data.nodes?.length) return;

    // Clear previous SVG only (keep grid/HUD)
    d3.select(container.value).select("svg").remove();

    const width = container.value.clientWidth;
    const height = container.value.clientHeight;

    const svg = d3.select(container.value)
        .append("svg")
        .attr("class", "absolute inset-0 z-10") // Removed pointer-events-none to allow zoom
        .attr("width", width)
        .attr("height", height)
        .attr("viewBox", [0, 0, width, height]);

    const g = svg.append("g"); // Main container for zoomable content

    // Zoom Behavior
    const zoom = d3.zoom()
        .scaleExtent([0.2, 3])
        .on("zoom", (event) => {
            currentTransform.value = event.transform;
            g.attr("transform", event.transform);
        });

    zoomBehavior.value = zoom;
    svg.call(zoom);

    // Disable individual zoom events on HUD elements if any (already handled by z-index/stop propagation)
    // Clear initial transform
    svg.call(zoom.transform, d3.zoomIdentity);

    // Create a deep copy of data
    const nodes = props.data.nodes.map(d => ({...d}));
    const links = props.data.links.map(d => ({...d}));

    // Simulation - Boosted strength and collision for better distribution
    const simulation = d3.forceSimulation(nodes as any)
        .force("link", d3.forceLink(links).id((d: any) => d.id).distance(150))
        .force("charge", d3.forceManyBody().strength(-800).distanceMax(500))
        .force("center", d3.forceCenter(width / 2, height / 2))
        .force("collide", d3.forceCollide().radius(75).strength(0.7));

    // Links - Inside 'g' for zoom
    const link = g.append("g")
        .selectAll("path")
        .data(links)
        .join("path")
        .attr("stroke", (d: any) => d.target.status >= 400 ? "#ef4444" : "#3b82f6")
        .attr("stroke-opacity", (d: any) => d.target.status >= 400 ? 0.6 : 0.3)
        .attr("stroke-width", 1.5)
        .attr("fill", "none")
        .style("filter", "drop-shadow(0 0 3px currentColor)");

    // Nodes (Phantom circles for physics/drag) - Inside 'g'
    const node = g.append("g")
        .selectAll("circle")
        .data(nodes)
        .join("circle")
        .attr("r", 30) // Match Vue circle radius
        .attr("fill", "transparent")
        .attr("cursor", "grab")
        .call(d3.drag() // @ts-ignore
            .on("start", dragstarted)
            .on("drag", dragged)
            .on("end", dragended) as any);

    simulation.on("tick", () => {
        link.attr("d", (d: any) => {
            const dx = d.target.x - d.source.x;
            const dy = d.target.y - d.source.y;
            const dr = Math.sqrt(dx * dx + dy * dy);
            // Curved Circuit Lines
            return `M${d.source.x},${d.source.y}A${dr},${dr} 0 0,1 ${d.target.x},${d.target.y}`;
        });

        node.attr("cx", (d: any) => d.x).attr("cy", (d: any) => d.y);
        
        // Sync Vue nodes
        iconNodes.value = [...nodes];
    });

    // Drag Functions
    function dragstarted(event: any, d: any) {
        if (!event.active) simulation.alphaTarget(0.3).restart();
        d.fx = d.x;
        d.fy = d.y;
    }

    function dragged(event: any, d: any) {
        d.fx = event.x;
        d.fy = event.y;
    }

    function dragended(event: any, d: any) {
        if (!event.active) simulation.alphaTarget(0);
        d.fx = null;
        d.fy = null;
    }
};

const resetZoom = () => {
    if (container.value && zoomBehavior.value) {
        const svg = d3.select(container.value).select("svg");
        svg.transition().duration(750).call(zoomBehavior.value.transform, d3.zoomIdentity);
    }
};

const iconNodes = ref<any[]>([]);

watch(() => props.data, renderGraph, { deep: true });

onMounted(() => {
    setTimeout(renderGraph, 100);
});
</script>

<template>
    <div class="w-full h-full relative group bg-slate-950 overflow-hidden rounded-xl border border-slate-800 cursor-grab active:cursor-grabbing" ref="container">
        
        <!-- Zoom Reset Control -->
        <div class="absolute bottom-6 right-6 z-40">
            <button 
                @click="resetZoom"
                class="p-2 rounded-lg bg-slate-900/80 backdrop-blur border border-slate-700 text-slate-400 hover:text-white hover:bg-slate-800 transition-all shadow-xl pointer-events-auto"
                title="Reset View"
            >
                <Zap class="w-4 h-4" />
            </button>
        </div>
        <!-- 1. Background Grid (Perspective Floor) -->
        <div class="absolute inset-0 pointer-events-none opacity-20"
             style="background: 
                linear-gradient(rgba(59, 130, 246, 0.4) 1px, transparent 1px), 
                linear-gradient(90deg, rgba(59, 130, 246, 0.4) 1px, transparent 1px);
                background-size: 60px 60px;
                transform: perspective(800px) rotateX(60deg) scale(2);
                transform-origin: 50% 100%;">
        </div>
        <!-- Horizon Fade -->
        <div class="absolute inset-0 pointer-events-none bg-gradient-to-b from-slate-950 via-slate-950/50 to-transparent h-1/2"></div>
        <div class="absolute inset-x-0 bottom-0 h-32 bg-gradient-to-t from-slate-950 to-transparent pointer-events-none"></div>

        <!-- 2. HUD: Top Header -->
        <div class="absolute top-0 left-0 right-0 p-4 flex justify-between items-start z-30 pointer-events-none">
            <!-- Left: Live Monitoring (Replaces 'SITE TOPOLOGY') -->
            <div class="flex items-center gap-2">
                <span class="relative flex h-2 w-2">
                    <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-emerald-400 opacity-75"></span>
                    <span class="relative inline-flex rounded-full h-2 w-2 bg-emerald-500"></span>
                </span>
                <span class="text-[10px] text-emerald-500 font-mono tracking-wider font-bold">LIVE MONITORING ACTIVE</span>
            </div>

            <!-- Right: Structural Integrity -->
            <div class="flex flex-col items-end w-48">
                <div class="flex justify-between w-full text-[10px] font-mono mb-1 text-slate-400">
                    <span>Structural Integrity</span>
                    <span>{{ stats.integrity }}%</span>
                </div>
                <div class="w-full h-2 bg-slate-800 rounded-full overflow-hidden border border-slate-700">
                    <div 
                        class="h-full bg-gradient-to-r from-emerald-600 to-emerald-400 transition-all duration-1000" 
                        :style="{ width: `${stats.integrity}%` }"
                    ></div>
                </div>
            </div>
        </div>

        <!-- 3. HUD: Bottom Stats (Moved slightly right) -->
        <div class="absolute bottom-6 left-6 z-30 pointer-events-none">
            <div class="bg-slate-900/80 backdrop-blur border border-slate-700 rounded-lg p-3 font-mono text-xs shadow-xl min-w-[140px]">
                <div class="flex justify-between gap-8 py-1 border-b border-slate-800">
                    <span class="text-slate-400">Nodes:</span>
                    <span class="text-white">{{ stats.total }}</span>
                </div>
                <div class="flex justify-between gap-8 py-1 border-b border-slate-800">
                    <span class="text-rose-400">Critical:</span>
                    <span class="text-rose-400 font-bold">{{ stats.critical }}</span>
                </div>
                <div class="flex justify-between gap-8 py-1">
                    <span class="text-emerald-400">Healthy:</span>
                    <span class="text-emerald-400 font-bold">{{ stats.healthy }}</span>
                </div>
            </div>
        </div>

        <!-- 4. Interactive Nodes (Transformed Wrapper) -->
        <div class="absolute inset-0 pointer-events-none z-20" :style="{ transform: `translate(${currentTransform.x}px, ${currentTransform.y}px) scale(${currentTransform.k})`, transformOrigin: '0 0' }">
            <div 
                v-for="node in iconNodes" 
                :key="node.id"
                class="absolute transform -translate-x-1/2 -translate-y-1/2 flex items-center justify-center will-change-transform pointer-events-auto"
                :style="{ left: `${node.x}px`, top: `${node.y}px`, width: '40px', height: '40px' }"
                @mouseenter="hoveredNode = node"
                @mouseleave="hoveredNode = null"
            >
            <!-- Node Content -->
            <div class="relative w-12 h-12 flex items-center justify-center">
                
                <!-- Special Effects based on Type -->
                
                <!-- ERROR / CRITICAL: Ripple Effect -->
                <div v-if="node.status >= 400" class="absolute inset-0 rounded-full border-2 border-rose-500/50 animate-ping"></div>
                <div v-if="node.status >= 400" class="absolute inset-[-4px] rounded-full border border-rose-500/30 animate-pulse"></div>

                <!-- ROOT: Orb Glow -->
                <div v-else-if="node.id === '/'" class="absolute inset-0 rounded-full bg-violet-500/20 blur-md animate-pulse"></div>
                <div v-else-if="node.id === '/'" class="absolute inset-[-8px] rounded-full border border-violet-500/30 opacity-50"></div>

                <!-- STANDARD: Outer Ring -->
                <div class="absolute inset-0 rounded-full border border-current opacity-60 shadow-[0_0_15px_currentColor] transition-all duration-300"
                     :class="{'scale-110': hoveredNode?.id === node.id}"
                     :style="{ color: getNodeColor(node) }"></div>
                
                <!-- Inner Bg -->
                <div class="absolute inset-0.5 rounded-full bg-slate-950/90 backdrop-blur z-0"></div>
                
                <!-- Icon -->
                <component 
                    :is="getIconComponent(node)" 
                    class="w-5 h-5 relative z-10 transition-transform duration-300" 
                    :class="{'scale-110': hoveredNode?.id === node.id}"
                    :style="{ color: getNodeColor(node) }"
                />
            </div>

            <!-- Absolute Tooltip (Only visible on hover) -->
            <transition
                enter-active-class="transition duration-200 ease-out"
                enter-from-class="transform scale-90 opacity-0 translate-y-2"
                enter-to-class="transform scale-100 opacity-100 translate-y-0"
                leave-active-class="transition duration-150 ease-in"
                leave-from-class="transform scale-100 opacity-100 translate-y-0"
                leave-to-class="transform scale-90 opacity-0 translate-y-2"
            >
                <div 
                    v-if="hoveredNode && hoveredNode.id === node.id"
                    class="absolute left-12 top-0 bg-slate-900/95 backdrop-blur border border-slate-700 rounded min-w-[180px] p-3 shadow-[0_0_30px_rgba(0,0,0,0.5)] pointer-events-none z-50 text-left"
                >
                    <!-- Tooltip Header -->
                    <div class="flex items-center gap-2 mb-2 border-b border-slate-800 pb-2">
                        <component :is="getIconComponent(node)" class="w-4 h-4" :style="{ color: getNodeColor(node) }" />
                        <span class="text-xs font-bold text-slate-200 font-mono truncate max-w-[120px]">{{ node.id === '/' ? 'ROOT' : node.id }}</span>
                    </div>
                    
                    <!-- Tooltip Metrics -->
                    <div class="space-y-2 font-mono text-[10px]">
                        <div class="flex justify-between items-center">
                            <span class="text-slate-500">STATUS</span>
                            <span :class="node.status >= 400 ? 'text-rose-400' : 'text-emerald-400'">{{ node.status }}</span>
                        </div>
                        <div class="flex justify-between items-center">
                            <span class="text-slate-500">LATENCY</span>
                            <span class="text-slate-300">{{ Math.floor(Math.random() * 200) + 20 }}ms</span>
                        </div>
                        <div class="flex justify-between items-center">
                            <span class="text-slate-500">THROUGHPUT</span>
                            <span class="text-slate-300">{{ Math.floor(Math.random() * 500) }} KB/s</span>
                        </div>
                    </div>

                    <!-- Scan Line Effect -->
                     <div class="absolute inset-0 bg-gradient-to-b from-transparent via-white/5 to-transparent h-[50%] animate-scan pointer-events-none"></div>
                </div>
            </transition>
        </div>
    </div>
</div>
</template>

<style scoped>
.animate-scan {
    animation: scan 2s linear infinite;
    background-size: 100% 200%;
}
@keyframes scan {
    0% { transform: translateY(-100%); }
    100% { transform: translateY(200%); }
}
</style>
