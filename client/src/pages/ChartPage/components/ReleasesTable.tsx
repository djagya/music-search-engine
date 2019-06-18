import styles from '../Chart.module.scss';
import React from 'react';
import { FIELDS, LABELS } from '../ChartPage';
import { Th } from './Grid';

// todo: implement sorting by timestamp for spins, and other fields for both indexes
export default function ReleasesTable({
  rows,
  charted,
  currentSort,
  onSortChange,
}: {
  rows: any[];
  charted: boolean;
  currentSort: string | null;
  onSortChange: any;
}) {
  return (
    <table className={styles.table}>
      <thead>
      <tr>
        {charted && <th>Rank</th>}
        <th>Count</th>
        <Th name="count" label="Count" sortable currentSort={currentSort} onSortChange={onSortChange} />

        <Th name={FIELDS.release} label={LABELS[FIELDS.release]} sortable currentSort={currentSort}
          onSortChange={onSortChange} />
        <Th name={FIELDS.artist} label={LABELS[FIELDS.artist]} />
        <Th name={'release_genre'} label="Genre" />
        <Th name={'label_name'} label="Label" />
        <Th name={'release_year_released'} label="Released" />
      </tr>
      </thead>

      <tbody>
      {rows.map(row => (
        <tr key={row._id}>
          {charted && <td>{row.rank}</td>}
          <td>{row.count}</td>

          <td>{row.release_title}</td>
          <td>{row.artist_name.join(', ')}</td>
          <td>{row.release_genre.join(', ')}</td>
          <td>{row.label_name.join(', ')}</td>
          <td>{row.release_year_released.join(', ')}</td>
        </tr>
      ))}
      </tbody>
    </table>
  );
}
