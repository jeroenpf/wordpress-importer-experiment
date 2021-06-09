export let LogComponent = {
	template: `
			<div>
				<h3><span class="dashicons dashicons-menu-alt"></span> Log entries:</h3>
				<table v-if="log_entries.length" class="wp-list-table widefat fixed striped table-view-list">
					<thead>
						<tr>
							<th>Message</th>
							<th>Date</th>
							<th>Level</th>
						</tr>
					</thead>
					<tbody>
						<tr v-for="log in log_entries"  :key="log.id" :class="log.meta.level">
							<td> {{ log.message }} </td>
							<td> {{ log.date }} </td>
							<td> <pre>{{ log.meta.level }}</pre> </td>
						</tr>
					</tbody>
				</table>
				<div v-else>
				  There are no log entries.
				</div>
			</div>
			`,
	data: () => {
		return {

		}
	},
	props: ['log_entries'],

};
