import styles from "../Chart.module.scss";
import React from "react";
import { FIELDS, LABELS } from "../ChartPage";
import { Th } from "./Grid";

/**
 * Group by release title.
 * Grouped columns: label, genre, release date.
 */
export default function ReleaseTable({ rows, charted }: { rows: any[]; charted: boolean }) {
  return (
    <table className={styles.table}>
      <thead>
      <th>Cover art</th>
      <Th name={FIELDS.release} label={LABELS[FIELDS.release]} />
      <Th name={FIELDS.artist} label={LABELS[FIELDS.artist]} />
      <th>Genres</th>
      <th>Labels</th>
      <th>Released</th>
      </thead>

      <tbody>
      <tr>
        <td>release</td>
        <td>artist1, artist2</td>
        <td>genre1, genre2</td>
        <td>label1, label2</td>
        <td>released1, released2</td>
      </tr>
      </tbody>
    </table>
  );
}
