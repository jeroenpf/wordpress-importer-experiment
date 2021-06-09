export let ImportSummaryComponent = {
	template: `
		<div>
			<h3><span class="dashicons dashicons-code-standards"></span> Import information:</h3>
			<table class="import wp-list-table widefat fixed striped table-view-list">
				<tr>
					<td>File</td>
					<td>{{ status.import.meta.wxr_file }}</td>
				</tr>
				<tr>
					<td>Checksum</td>
					<td>{{ status.import.meta.wxr_file_checksum }}</td>
				</tr>
				<tr>
					<td>Status</td>
					<td :class="['status', status.import.meta.status]">{{ status.import.meta.status }}</td>
				</tr>
			</table>
		</div>

			`,
	data: () => {
		return {

		}
	},
	props: ['status'],

};
