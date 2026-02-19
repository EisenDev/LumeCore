<script setup lang="ts">
/**
 * ToxicityTreemap.vue
 * Visualizes Code Toxicity (Churn vs Complexity/Size) using D3 Treemap.
 * Color Scale: Green (Low Churn) -> Red (High Churn)
 */
import { onMounted, ref, watch } from 'vue';
import * as d3 from 'd3';

interface FileStat {
    name: string;
    churn: number;
    commits: number;
}

const props = defineProps<{
    data: FileStat[] | Record<string, any>;
}>();

const container = ref<HTMLElement | null>(null);

function renderChart() {
    if (!container.value || !props.data) return;
    
    // Normalize Data: Convert Object or Array to Hierarchy
    let cleanData: { name: string; value: number; churn: number }[] = [];
    
    if (Array.isArray(props.data)) {
        cleanData = props.data.map(f => ({
            name: f.name || 'Unknown', 
            // If churn is big, value is big. We can use churn for size too.
            value: f.churn || 1, 
            churn: f.churn || 0
        }));
    } else {
        // Handle object format (filename => {churn, commits})
        cleanData = Object.entries(props.data).map(([name, stats]) => ({
            name: name,
            value: stats.churn || 1,
            churn: stats.churn || 0
        }));
    }

    if (cleanData.length === 0) return;

    // Clear previous
    d3.select(container.value).selectAll("*").remove();

    const width = container.value.clientWidth;
    const height = 300; // Fixed height for widgets

    const svg = d3.select(container.value)
        .append("svg")
        .attr("width", width)
        .attr("height", height)
        .style("font-family", "monospace");

    // Stratify data
    const root = d3.hierarchy({ name: "root", children: cleanData })
        .sum((d: any) => d.value)
        .sort((a, b) => (b.value || 0) - (a.value || 0));

    // Treemap Layout
    d3.treemap()
        .size([width, height])
        .padding(2)
        .round(true)
        (root);

    // Color Scale (Standard Deviation based?)
    // Simple: 0 -> Max Churn
    const maxChurn = d3.max(cleanData, d => d.churn) || 100;
    const colorScale = d3.scaleSequential()
        .domain([0, maxChurn])
        .interpolator(d3.interpolateRgb("rgba(16, 185, 129, 0.3)", "rgba(244, 63, 94, 0.8)")); 
        // Emerald to Rose

    const nodes = svg.selectAll("g")
        .data(root.leaves())
        .enter()
        .append("g")
        .attr("transform", d => `translate(${d.x0},${d.y0})`);

    // Rectangles
    nodes.append("rect")
        .attr("width", d => d.x1 - d.x0)
        .attr("height", d => d.y1 - d.y0)
        .attr("fill", (d: any) => colorScale(d.data.churn))
        .attr("stroke", "#0a0f1a")
        .attr("rx", 4);

    // Labels (only if big enough)
    nodes.append("text")
        .attr("x", 4)
        .attr("y", 14)
        .text((d: any) => {
            const width = d.x1 - d.x0;
            return width > 50 ? d.data.name.split('/').pop() : ""; 
        })
        .attr("font-size", "10px")
        .attr("fill", "white")
        .attr("opacity", 0.9)
        .style("pointer-events", "none");

    // Interactive Tooltip provided by parent or native title
    nodes.append("title")
        .text((d: any) => `${d.data.name}\nChurn: ${d.data.churn} lines changed`);
}

onMounted(() => {
    // Wait for container size
    setTimeout(renderChart, 100);
});

watch(() => props.data, renderChart, { deep: true });
</script>

<template>
    <div ref="container" class="w-full h-[300px] overflow-hidden rounded-lg bg-black/20"></div>
</template>
