import styles from "../Chart.module.scss";
import React from "react";
import { FIELDS, LABELS } from "../ChartPage";
import { Th } from "./Grid";

/**
 * Group by artist name.
 * Grouped columns: label.
 */
export default function ArtistTable({ rows, charted }: { rows: any[]; charted: boolean }) {
  return (
    <table className={styles.table}>
      <thead>
      {charted && <th>Rank</th>}

      <Th name={FIELDS.artist} label={LABELS[FIELDS.artist]} />
      <th>Labels</th>
      </thead>

      <tbody>
      {rows.map(row => (
        <tr key={row._id}>
          <td>{row.artist_name}</td>
          <td>{JSON.stringify(row.label_name)}</td>
        </tr>
      ))}
      </tbody>
    </table>
  );
}
