function roseFromExpo(id, expos1Str, expos2Str, width, height) {
  const expos1 = [
    ...expos1Str
      .replaceAll("'", "")
      .split(",")
      .map(toId)
      .filter((e) => e !== -1),
    ...findImplicitSubSectors(expos1Str),
  ];
  const expos2 = [
    ...expos2Str
      .replaceAll("'", "")
      .split(",")
      .map(toId)
      .filter((e) => e !== -1),
    ...findImplicitSubSectors(expos2Str),
  ];
  const svg = d3
    .select("#" + id)
    .append("svg")
    .attr("viewBox", "-150 -150 300 300")
    .attr("width", width)
    .attr("height", height);
  const numSectors = 16;
  const radius = 120;
  const innerRadius = radius * 0.6; // Taille du centre de l'étoile
  const angleStep = (2 * Math.PI) / numSectors;

  // Création des secteurs
  for (let i = 0; i < numSectors; i++) {
    const angle = i * angleStep;
    const midAngle = angle + angleStep / 2;
    const r2 = radius * 0.9;
    const r3 = radius * 0.75;
    const r = i % 4 === 0 ? radius : i % 2 === 0 ? r2 : r3;
    const rprev = (i + 1) % 4 === 0 ? radius : (i + 1) % 2 === 0 ? r2 : r3;

    const x1 = Math.cos(angle) * r;
    const y1 = Math.sin(angle) * r;
    const x2 = Math.cos(midAngle) * innerRadius;
    const y2 = Math.sin(midAngle) * innerRadius;
    const x3 = Math.cos(angle + angleStep) * rprev;
    const y3 = Math.sin(angle + angleStep) * rprev;

    svg
      .append("polygon")
      .attr("points", `0,0 ${x1},${y1} ${x2},${y2} 0,0`)
      .attr(
        "fill",
        expos1.includes(i % 16)
          ? "#1e5d3e"
          : expos2.includes(i % 16)
          ? "#1e5d3e88"
          : "#eee"
      )
      .attr(
        "stroke",
        expos1.includes(i % 16)
          ? "#2e8b57"
          : expos2.includes(i % 16)
          ? "#2e8b5788"
          : "#bbb"
      )
      .attr("stroke-width", 1);
    svg
      .append("polygon")
      .attr("points", `0,0 ${x3},${y3} ${x2},${y2} 0,0`)
      .attr(
        "fill",
        expos1.includes((i + 1) % 16)
          ? "#2e8b57"
          : expos2.includes((i + 1) % 16)
          ? "#2e8b5788"
          : "#bbb"
      )
      .attr(
        "stroke",
        expos1.includes((i + 1) % 16)
          ? "#2e8b57"
          : expos2.includes((i + 1) % 16)
          ? "#2e8b5788"
          : "#bbb"
      )
      .attr(
        "stroke-width",
        expos1.includes((i + 1) % 16) || expos2.includes((i + 1) % 16) ? 2 : 1
      );
  }

  // Cercle central
  svg
    .append("circle")
    .attr("cx", 0)
    .attr("cy", 0)
    .attr("r", innerRadius * 0.1)
    .attr("fill", "#2e8b57");
}

const toId = (e) => {
  const arr = "E,ESE,SE,SSE,S,SSO,SO,OSO,O,ONO,NO,NNO,N,NNE,NE,ENE".split(",");
  return arr.indexOf(e);
};
const findImplicitSubSectors = (exposStr) => {
  const subsectors = [
    { name: "ESE", needs: ["E", "SE"] },
    { name: "SSE", needs: ["SE", "S"] },
    { name: "SSO", needs: ["S", "SO"] },
    { name: "OSO", needs: ["SO", "O"] },
    { name: "ONO", needs: ["O", "NO"] },
    { name: "NNO", needs: ["NO", "N"] },
    { name: "NNE", needs: ["N", "NE"] },
    { name: "ENE", needs: ["NE", "E"] },
  ];
  const expos = exposStr.replaceAll("'", "").split(",");
  const includedSectors = subsectors.filter((s) =>
    s.needs.every((n) => expos.includes(n))
  );
  return includedSectors.map((s) => toId(s.name));
};
